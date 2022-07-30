<?php

declare(strict_types=1);

namespace JUser\Controller;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Authentication\Result;
use Laminas\InputFilter\Input;
use Laminas\Log\LoggerInterface;
use Laminas\Mail\Message;
use Laminas\Mail\MessageFactory;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Math\Rand;
use Laminas\Mvc\I18n\Translator;
use Laminas\Validator\EmailAddress;
use Laminas\View\Model\JsonModel;
use LmcUser\Entity\UserInterface;
use LmcUser\Form\LoginFilter;
use Locale;
use RestApi\Controller\ApiController;
use Webmozart\Assert\Assert;

use function array_shift;
use function call_user_func;
use function count;
use function gettype;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;
use function method_exists;
use function sprintf;

class LoginV1ApiController extends ApiController
{
    protected const LOGIN_ACTION_VERIFICATION_TOKEN_REQUEST = 'verification-token-request';
    protected const LOGIN_ACTION_AUTHENTICATE_BY_CREDENTIAL = 'authenticate';
    private const TRANSLATOR_TEXT_DOMAIN                    = 'JUser';

    /**
     * @param callable $apiVerificationRequestNonRegisteredUserEmailHandler
     */
    public function __construct(
        private CredentialOrTokenQueryParams $adapter,
        private UserTable $table,
        private TransportInterface $mailTransport,
        private LoggerInterface $logger,
        private Translator $translator,
        private LoginFilter $loginFilter,
        private $apiVerificationRequestNonRegisteredUserEmailHandler,
        private array $config,
    ) {
        Assert::isCallable($this->apiVerificationRequestNonRegisteredUserEmailHandler);
    }

    public function loginAction(): JsonModel
    {
        //@TODO it's imperative that this function gets a speed limit to prevent brute forcing

        //make sure verb is GET
        if ('GET' !== $this->getRequest()->getMethod()) {
            $this->httpStatusCode         = 400;
            $this->apiResponse['message'] = $this->translate('This method only accepts GET requests.');
            return $this->createResponse();
        }

        //@todo should we do some validation first?

        $authResult = $this->adapter->authenticate();
        if (! $authResult->isValid()) {
            if (Result::FAILURE_UNCATEGORIZED === $authResult->getCode()) {
                $this->httpStatusCode = 400;
            } else {
                $this->httpStatusCode = 401;
            }
            $this->apiResponse['message'] = $this->translate($authResult->getMessages()[0]);
            return $this->createResponse();
        }
        $userId      = $authResult->getIdentity()['id'];
        $jwtResponse = $this->getNewJwtTokenResponse($userId);

        $this->httpStatusCode = 200;
        $this->apiResponse    = $jwtResponse;
        return $this->createResponse();
    }

    /**
     * Email the user a verification token iff:
     * 1. They have a user or the consuming package finds/creates one for us
     * 2. The user isActive
     * 3. The user is not a multi-person user
     */
    public function requestVerificationTokenAction(): JsonModel
    {
        $identityParam = $this->params()->fromQuery('identity');
        if (! $this->isIdentityValueValid($identityParam)) {
            $this->getLogger()->info(
                'JUser: A verification token was requested for an invalid identity',
                ['identity' => $identityParam]
            );
            $this->apiResponse['message'] = $this->translate("Invalid identity parameter.");
            $this->httpStatusCode         = 400;
            return $this->createResponse();
        }

        $userObject = $this->lookupUserObject($identityParam);
        //just check the identity parameter, look them up and send an email
        if ($userObject) {
            $acceptedStates = $this->config['lmcuser']['allowed_login_states'];
            if (! in_array($userObject->getState(), $acceptedStates)) {
                $this->getLogger()->info(
                    'JUser: A verification token was requested for an identity with an invalid state',
                    ['identity' => $identityParam, 'state' => $userObject->getState()]
                );
                $this->httpStatusCode         = 401;
                $this->apiResponse['message'] = $this->translate('Inactive users may not use this API endpoint');
                return $this->createResponse();
            }
            if (method_exists($userObject, 'getMultiPersonUser') && $userObject->getMultiPersonUser()) {
                $this->getLogger()->notice(
                    'JUser: A verification token was requested (and denied) for a multi-person user',
                    ['identity' => $identityParam]
                );
                $this->httpStatusCode         = 401;
                $this->apiResponse['message'] = $this->translate('Multi-person users may not use this API endpoint');
                return $this->createResponse();
            }
            if (! $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail())) {
                //problem logging is handled by createAndSendVerificationEmail
                $this->httpStatusCode         = 500;
                $this->apiResponse['message'] = $this->translate('Error sending verification email');
                return $this->createResponse();
            }
            $message                      = $this->config['juser']['api_verification_token_sent_response_text'];
            $this->apiResponse['message'] = $this->translate($message);
            $this->httpStatusCode         = 200;
            return $this->createResponse();
        } elseif ($this->isEmailAddress($identityParam)) {
            if (isset($this->apiVerificationRequestNonRegisteredUserEmailHandler)) {
                $this->getLogger()->notice(
                    'JUser: A verification token was requested, but no user exists. '
                    . 'Calling apiVerificationRequestNonRegisteredUserEmailHandler to see if they give us a user.',
                    [
                        'identity' => $identityParam,
                        'apiVerificationRequestNonRegisteredUserEmailHandler'
                            => $this->apiVerificationRequestNonRegisteredUserEmailHandler,
                    ]
                );
                $userObject = call_user_func(
                    $this->apiVerificationRequestNonRegisteredUserEmailHandler,
                    $identityParam
                );
                if ($userObject instanceof UserInterface) {
                    $this->getLogger()->notice(
                        'JUser: Calling apiVerificationRequestNonRegisteredUserEmailHandler gave us a User!',
                        [
                            'identity' => $identityParam,
                            'userId'   => $userObject->getId(),
                            'username' => $userObject->getUsername(),
                        ]
                    );
                    if (! $this->createAndSendVerificationEmail((int) $userObject->getId(), $userObject->getEmail())) {
                        //logging is handled by createAndSendVerificationEmail
                        $this->httpStatusCode         = 500;
                        $this->apiResponse['message'] = $this->translate('Error sending verification email');
                        return $this->createResponse();
                    }
                    $this->httpStatusCode         = 200;
                    $message                      = $this->config['juser']['api_verification_token_sent_response_text'];
                    $this->apiResponse['message'] = $this->translate($message);
                    return $this->createResponse();
                } else {
                    $returnType = gettype($userObject);
                    if ('object' === $returnType) {
                        $returnType = $userObject::class;
                    }
                    $this->getLogger()->notice(
                        'JUser: Calling apiVerificationRequestNonRegisteredUserEmailHandler didn\'t give us a User',
                        [
                            'identity'   => $identityParam,
                            'returnType' => $returnType,
                        ]
                    );
                }
            }
        }
        //there's nothing we can do here. We didn't find the user
        $this->httpStatusCode         = 403;
        $this->apiResponse['message'] = $this->translate('Provided identity was not found');
        return $this->createResponse();
    }

    /**
     * give the user a JWT iff:
     * 1. they have an account
     * 2. they gave a valid (non-expired) token corresponding to their account
     */
    public function loginWithVerificationTokenAction(): JsonModel
    {
        //@todo do more logging here
        $identityParam = $this->params()->fromQuery('identity');
        if (! $this->isIdentityValueValid($identityParam)) {
            $this->apiResponse['message'] = $this->translate("Invalid identity parameter.");
            $this->httpStatusCode         = 400;
            return $this->createResponse();
        }
        $userObject = $this->lookupUserObject($identityParam);
        //just check the identity parameter, look them up and send an email
        if (! is_object($userObject) || ! is_numeric($userObject->getId())) {
            $this->apiResponse['message'] = $this->translate("User identity not found.");
            $this->httpStatusCode         = 401;
            return $this->createResponse();
        }
        $id         = $userObject->getId();
        $userRecord = $this->table->getUser($id);
        $objects    = $this->table->queryObjects('user', ['userId' => $id]);
        Assert::isArray($objects);
        $this->table->linkUsers($objects);
        Assert::notEmpty($objects);
        Assert::keyExists($objects, $id);
        $userRecord = $objects[$id];
        $token      = $this->params()->fromQuery('token');
        $now        = new DateTime();
        if (
            ! isset($userRecord['verificationToken'])
            || ! isset($token)
            || ! isset($userRecord['verificationExpiration'])
            || ! $userRecord['verificationExpiration'] instanceof DateTimeInterface
            || $userRecord['verificationExpiration'] < $now
        ) {
            $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail());
            $this->apiResponse['message'] = $this->translate(
                "We could not verify the identity, an email has been resent to the user."
            );
            $this->httpStatusCode         = 401;
            return $this->createResponse();
        }
        if ($userRecord['verificationToken'] !== $token) {
            $this->apiResponse['message'] = $this->translate("Invalid token.");
            $this->httpStatusCode         = 401;
            return $this->createResponse();
        }

        //delete the token to prevent replay attacks; mark the email verified
        try {
            $this->table->updateEntity(
                'user',
                $id,
                [
                    'verificationToken'      => null,
                    'verificationExpiration' => null,
                    'emailVerified'          => 1,
                ]
            );
        } catch (Exception $e) {
            $this->getLogger()->err(
                'There was a db failure wiping the verification token',
                ['message' => $e->getMessage()]
            );
        }
        $this->apiResponse    = $this->getNewJwtTokenResponse($userObject->getId());
        $this->httpStatusCode = 200;
        return $this->createResponse();
    }

    /**
     * Simplify the process of checking if we have a translator and using the correct domain/locale
     */
    protected function translate(string $message): string
    {
        if ($this->getTranslator()) {
            $message = $this->getTranslator()->translate(
                $message,
                self::TRANSLATOR_TEXT_DOMAIN,
                Locale::getDefault()
            );
        }
        return $message;
    }

    protected function lookupUserObject(string $identityParam): UserInterface|null
    {
        static $fields;
        if (! isset($fields)) {
            $fields = $this->adapter->getOptions()->getAuthIdentityFields();
        }
        $userObject = null;
        while (count($fields) > 0 && ! isset($userObject)) {
            $mode = array_shift($fields);
            switch ($mode) {
                case 'username':
                    $userObject = $this->table->findByUsername($identityParam);
                    break;
                case 'email':
                    $userObject = $this->table->findByEmail($identityParam);
                    break;
            }
        }
        return $userObject;
    }

    /**
     * Create a verification token and send it to the user
     *
     * @return boolean
     */
    protected function createAndSendVerificationEmail(int $userId, string $userEmail)
    {
        try { //there could be db errors here
            $verificationToken = $this->createUserVerificationToken($userId);
        } catch (Exception $e) {
            $this->getLogger()->crit(
                'JUser: Error recording new verification token in db',
                ['userId' => $userId, 'message' => $e->getMessage()]
            );
            return false;
        }
        $message = $this->createVerificationEmail($verificationToken, $userEmail);
        $this->getLogger()->info('JUser: About to attempt sending verification email to user', ['userId' => $userId]);
        try {
            $this->mailTransport->send($message);
        } catch (Exception $e) {
            $this->getLogger()->crit(
                'JUser: Error sending verification email to user',
                ['userId' => $userId, 'message' => $e->getMessage()]
            );
            return false;
        }
        $this->getLogger()->info('JUser: Sent verification email to user', ['userId' => $userId]);
        return true;
    }

    /**
     * Generate a new verification token and save it to the user table in the database
     */
    protected function createUserVerificationToken(int $userId): string
    {
        $token              = User::generateVerificationToken($this->config['juser']['api_verification_token_length']);
        $expirationInterval = $this->config['juser']['api_verification_token_expiration_interval'];
        $expiration         = new DateTime('now', new DateTimeZone('UTC'));
        $expiration->add(new DateInterval($expirationInterval));
        $this->table->updateEntity(
            'user',
            $userId,
            ['verificationToken' => $token, 'verificationExpiration' => $expiration]
        );
        $this->getLogger()->info('JUser: A new API verification token was recorded in the db', ['userId' => $userId]);
        return $token;
    }

    /**
     * Generate an email for the user
     */
    protected function createVerificationEmail(string $token, string $to): Message
    {
        $messageConfig = $this->config['juser']['verification_email_message'];
        $body          = $messageConfig['body'];
        if ($this->getTranslator()) {
            $messageConfig['subject'] = $this->getTranslator()->translate(
                $messageConfig['subject'],
                self::TRANSLATOR_TEXT_DOMAIN,
                Locale::getDefault()
            );
            $body                     = $this->getTranslator()->translate(
                $body,
                self::TRANSLATOR_TEXT_DOMAIN,
                Locale::getDefault()
            );
        }
        $messageConfig['body'] = sprintf($body, $token);
        $message               = MessageFactory::getInstance($messageConfig);
        $message->addTo($to);
        return $message;
    }

    /**
     * Creates a new JWT token from the juser config options and returns it in an
     * array format that may be returned as a response
     *
     * @return string[]
     */
    protected function getNewJwtTokenResponse(int $userId)
    {
        static $options;
        if (! is_array($options)) {
            $config = $this->config['juser'];
            Assert::keyExists($this->config['juser'], 'jwt_id_length');
            Assert::greaterThanEq($config['jwt_id_length'], 6);
            Assert::keyExists($this->config['juser'], 'jwt_id_charlist');
            Assert::keyExists($this->config['juser'], 'jwt_expiration_interval');
            $options = [
                'jwt_id_length'           => $config['jwt_id_length'],
                'jwt_id_charlist'         => $config['jwt_id_charlist'],
                'jwt_expiration_interval' => $config['jwt_expiration_interval'],
            ];
        }
        $jwtId      = Rand::getString($options['jwt_id_length'], $options['jwt_id_charlist']);
        $expiration = new DateTime('now', new DateTimeZone('UTC'));
        $expiration->add(new DateInterval($options['jwt_expiration_interval']));
        $payload = [
            'sub' => $userId,
            'exp' => $expiration->format('U'),
            'jti' => $jwtId,
        ];
        $jwt     = $this->generateJwtToken($payload);
        //@todo record the creation in the table
        $this->getLogger()->info('JUser: JWT created', ['userId' => $userId, 'jwtId' => $jwtId]);
        return ['jwt' => $jwt, 'expiration' => $expiration->format('Y-m-d\TH:i:s\Z')];
    }

    /**
     * Doesn't look up the user, just checks that it's a possible value
     */
    protected function isIdentityValueValid(string $value): bool
    {
        /** @var Input $identityInput */
        $identityInput = $this->loginFilter->get('identity');
        $identityInput->setValue($value);
        //@todo this shouldn't accept 'je', but it does
        return $identityInput->isValid();
    }

    /**
     * Check if a string is an email address
     */
    protected static function isEmailAddress(string $text): bool
    {
        static $validator;
        if (! isset($validator)) {
            $validator = new EmailAddress();
        }
        return $validator->isValid($text);
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }
}
