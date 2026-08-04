<?php

namespace JUser\Controller;

use Carbon\Carbon;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Psr\Log\LoggerInterface;
use Laminas\Math\Rand;
use Laminas\Validator\EmailAddress;
use RestApi\Controller\ApiController;

/**
 * Passwordless sign-in for API clients.
 *
 * Two steps, both keyed on an email address or username:
 *  1. request-verification-token: we mail a short single-use code
 *  2. login-with-verification-token: the code is exchanged for a JWT
 *
 * There is no credential/password grant. There never will be again.
 */
class LoginV1ApiController extends ApiController
{
    /** @var UserTable $table */
    protected $table;

    /** @var LoginTokenService $tokenService */
    protected $tokenService;

    /** @var Mailer $mailer */
    protected $mailer;

    /**
     * Application config
     * @var array $config
     */
    protected $config;

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(
        UserTable $table,
        LoginTokenService $tokenService,
        Mailer $mailer,
        array $config
    ) {
        $this->table = $table;
        $this->tokenService = $tokenService;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    /**
     * The old password grant used to live here. It's gone.
     */
    public function loginAction()
    {
        $this->httpStatusCode = 400;
        $this->apiResponse['message'] = 'Passwords are no longer accepted. Request a login code at '
            . '/api/v1/users/request-verification-token and exchange it at '
            . '/api/v1/users/login-with-verification-token.';
        return $this->createResponse();
    }

    /**
     * Mail a short login code to the account matching the given identity.
     * The response never reveals whether the account exists.
     */
    public function requestVerificationTokenAction()
    {
        $identityParam = $this->getIdentityParam();

        if (null !== $identityParam) {
            try {
                $userObject = $this->lookupUserObject($identityParam);
                if ($userObject instanceof User && $this->tokenService->mayIssueToken($userObject)) {
                    $code = $this->tokenService->issueApiCode($userObject);
                    $this->mailer->sendLoginCodeEmail(
                        $userObject,
                        $code,
                        $this->tokenService->getApiCodeExpirationMinutes()
                    );
                }
            } catch (\Exception $e) {
                //deliberately swallowed: the caller learns nothing either way
                if (isset($this->logger)) {
                    $this->logger->error("JUser: Failed to issue an API login code.", ['exception' => $e]);
                }
            }
        }

        $this->httpStatusCode = 200;
        $this->apiResponse['message'] = 'If that account exists, a login code is on its way by email.';
        return $this->createResponse();
    }

    /**
     * Exchange a valid login code for a JWT.
     */
    public function loginWithVerificationTokenAction()
    {
        $identityParam = $this->getIdentityParam();
        $token = $this->params()->fromQuery('token', $this->params()->fromPost('token'));

        if (null === $identityParam || ! is_string($token) || '' === trim($token)) {
            $this->apiResponse['message'] = 'Please provide both an identity and a token.';
            $this->httpStatusCode = 400;
            return $this->createResponse();
        }

        $userObject = $this->lookupUserObject($identityParam);
        if (! $userObject instanceof User || ! is_numeric($userObject->getId())) {
            //same message as a bad token, so we don't confirm which accounts exist
            $this->apiResponse['message'] = 'Invalid or expired token.';
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }

        if (! $this->tokenService->redeemTokenForUser($userObject, $token)) {
            $this->apiResponse['message'] = 'Invalid or expired token.';
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }

        //redeeming the code proves the address works, so the account goes live
        if (1 != $userObject->getState()) {
            $this->table->activateUser($userObject->getId());
        }

        $this->apiResponse = $this->getNewJwtTokenResponse($userObject->getId());
        $this->httpStatusCode = 200;
        return $this->createResponse();
    }

    /**
     * @return string|null
     */
    protected function getIdentityParam()
    {
        $identity = $this->params()->fromQuery('identity', $this->params()->fromPost('identity'));
        if (! is_string($identity)) {
            return null;
        }
        $identity = trim($identity);
        return '' === $identity ? null : $identity;
    }

    /**
     * @param string $identityParam
     * @return User|null
     */
    protected function lookupUserObject($identityParam)
    {
        if (self::isEmailAddress($identityParam)) {
            $userObject = $this->table->findByEmail($identityParam);
            if ($userObject instanceof User) {
                return $userObject;
            }
        }
        return $this->table->findByUsername($identityParam);
    }

    protected function getNewJwtTokenResponse($userId)
    {
        $jwtId = Rand::getString(10);
        $expiration = Carbon::now()
        ->addMonths(6); //@todo make configurable
        $payload = [
            'sub' => $userId,
            'exp' => $expiration->format('U'), //'Y-m-d\TH:i:s\Z'),
            'jti' => $jwtId,
        ];
        $jwt = $this->generateJwtToken($payload);
        return ['jwt' => $jwt, 'expiration' => $expiration->format('Y-m-d\TH:i:s\Z')];
    }

    /**
     * Check if a string is an email address
     * @param string $text
     * @return boolean
     */
    protected static function isEmailAddress($text)
    {
        static $validator;
        if (! isset($validator)) {
            $validator = new EmailAddress();
        }
        return $validator->isValid($text);
    }

    /**
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
