<?php

namespace JUser\Controller;

use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Service\ApiTokenService;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Psr\Log\LoggerInterface;
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

    /** @var LoginTokenService $loginTokenService */
    protected $loginTokenService;

    /** @var Mailer $mailer */
    protected $mailer;

    /** @var ApiTokenService $tokenService */
    protected $tokenService;

    /**
     * Application config
     * @var array $config
     */
    protected $config;

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(
        UserTable $table,
        LoginTokenService $loginTokenService,
        Mailer $mailer,
        array $config,
        ApiTokenService $tokenService
    ) {
        $this->table = $table;
        $this->loginTokenService = $loginTokenService;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->tokenService = $tokenService;
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
                if ($userObject instanceof User && $this->loginTokenService->mayIssueToken($userObject)) {
                    $code = $this->loginTokenService->issueApiCode($userObject);
                    $this->mailer->sendLoginCodeEmail(
                        $userObject,
                        $code,
                        $this->loginTokenService->getApiCodeExpirationMinutes()
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

        if (! $this->loginTokenService->redeemTokenForUser($userObject, $token)) {
            $this->apiResponse['message'] = 'Invalid or expired token.';
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }

        //redeeming the code proves the address works, so the account goes live
        if (1 != $userObject->getState()) {
            $this->table->activateUser($userObject->getId());
        }

        $this->apiResponse = $this->getNewJwtTokenResponse($userObject);
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

    /**
     * Mint the JWT this sign-in earned.
     *
     * Delegated to ApiTokenService rather than built here, and that is the whole
     * point of the refactor: this method used to generate a `jti`, sign a payload
     * and drop the identifier on the floor, so nothing anywhere recorded that a
     * credential had been handed out. Now the same call that signs also registers,
     * which is what lets a token be revoked before its six months are up.
     *
     * The response shape is unchanged — {jwt, expiration} — because the mobile
     * apps read it.
     *
     * @param User $userObject
     * @return array
     */
    protected function getNewJwtTokenResponse(User $userObject)
    {
        $issued = $this->tokenService->issue($userObject);

        return [
            'jwt' => $issued['jwt'],
            'expiration' => $issued['expiration']->format('Y-m-d\TH:i:s\Z'),
        ];
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
