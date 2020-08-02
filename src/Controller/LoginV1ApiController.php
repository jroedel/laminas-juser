<?php

namespace JUser\Controller;

use RestApi\Controller\ApiController;
use Zend\Validator\EmailAddress;
use Zend\Math\Rand;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use JUser\Model\UserTable;
use Zend\Mail\Transport\TransportInterface;
use JUser\Model\User;
use Zend\Log\LoggerAwareTrait;
use Zend\InputFilter\InputFilterInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class LoginV1ApiController extends ApiController
{
    use LoggerAwareTrait;
    use TranslatorAwareTrait;
    
    protected const LOGIN_ACTION_VERIFICATION_TOKEN_REQUEST = 'verification-token-request';
    protected const LOGIN_ACTION_AUTHENTICATE_BY_CREDENTIAL = 'authenticate';

    /**
     * @var CredentialOrTokenQueryParams $adapter
     */
    protected $adapter;

    /**
     * @var UserTable $table
     */
    protected $table;
    
    /**
     * @var TransportInterface $mailTransport
     */
    protected $mailTransport;
    
    /**
     * Application config
     * @var array $config
     */
    protected $config;
    
    /**
     * Set by the api_verification_request_non_registered_user_email_handler
     * @var callable $apiVerificationRequestNonRegisteredUserEmailHandler
     */
    protected $apiVerificationRequestNonRegisteredUserEmailHandler;

    /**
     * @var InputFilterInterface $loginFilter
     */
    protected ?InputFilterInterface $loginFilter = null;
    
    public function __construct(
        CredentialOrTokenQueryParams $adapter,
        UserTable $table, 
        TransportInterface $mailTransport,
        array $config
    ) {
        $this->adapter = $adapter;
        $this->table = $table;
        $this->mailTransport = $mailTransport;
        $this->config = $config;
    }
    
    public function loginAction()
    {
        //@TODO it's imperative that this function gets a speed limit to prevent brute forcing
        
        //make sure verb is GET
        if ('GET' !== $this->getRequest()->getMethod()) {
            $this->httpStatusCode = 400;
            $this->apiResponse['message'] = 'This method only accepts GET requests.';
            return $this->createResponse();
        }
        
        //@todo should we do some validation first?

        /**
         * @var \Zend\Authentication\Result $auth
         */
        $authResult = $this->adapter->authenticate();
        if (! $authResult->isValid()) {
            if (\Zend\Authentication\Result::FAILURE_UNCATEGORIZED === $authResult->getCode()) {
                $this->httpStatusCode = 400;
            } else {
                $this->httpStatusCode = 401;
            }
            $this->apiResponse['message'] = $authResult->getMessages()[0];
            return $this->createResponse();
        }
        $userId = $authResult->getIdentity()['id'];
        $jwtResponse = $this->getNewJwtTokenResponse($userId);
        
        $this->httpStatusCode = 200;
        $this->apiResponse = $jwtResponse;
        return $this->createResponse();
    }
    
    /**
     * Email the user a verification token iff:
     * 1. They have a user or the consuming package finds/creates one for us
     * 2. The user isActive
     * 3. The user is not a multi-person user
     * @return \Zend\View\Model\JsonModel
     */
    public function requestVerificationTokenAction()
    {
        //@todo add translation into this
        $identityParam = $this->params()->fromQuery('identity');
        if (! $this->isIdentityValueValid($identityParam)) {
            $this->apiResponse['message'] = "Invalid identity parameter.";
            $this->httpStatusCode = 400;
            return $this->createResponse();
        }
        
        $userObject = $this->lookupUserObject($identityParam);
        //just check the identity parameter, look them up and send an email
        if ($userObject) {
            $acceptedStates = $this->config['zfcuser']['allowed_login_states'];
            if (! in_array($userObject->getState(), $acceptedStates)) {
                $this->httpStatusCode = 401;
                $this->apiResponse['message'] = 'Inactive users may not use this API endpoint';
                return $this->createResponse();
            }
            if (method_exists($userObject, 'getMultiPersonUser') && $userObject->getMultiPersonUser()) {
                $this->httpStatusCode = 401;
                $this->apiResponse['message'] = 'Multi-person users may not use this API endpoint';
                return $this->createResponse();
            }
            if (! $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail())) {
                $this->httpStatusCode = 500;
                $this->apiResponse['message'] = 'Error sending verification email';
                return $this->createResponse();
            }
            $this->httpStatusCode = 200;
            $this->apiResponse['message'] = 'You\'ll be getting an email with your verification token.';
            return $this->createResponse();
        } elseif ($this->isEmailAddress($identityParam)) {
            if (isset($this->apiVerificationRequestNonRegisteredUserEmailHandler)) {
                $userObject = call_user_func(
                    $this->apiVerificationRequestNonRegisteredUserEmailHandler, 
                    $identityParam
                    );
                if ($userObject instanceof \ZfcUser\Entity\UserInterface) {
                    if (! $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail())) {
                        $this->httpStatusCode = 500;
                        $this->apiResponse['message'] = 'Error sending verification email';
                        return $this->createResponse();
                    }
                    $this->httpStatusCode = 200;
                    $this->apiResponse['message'] = 'Hang in there champ, you\'ll be getttin that email.';
                    return $this->createResponse();
                }
            }
        }
        //there's nothing we can do here. We didn't find the user
        $this->httpStatusCode = 403;
        $this->apiResponse['message'] = 'Provided identity was not found';
        return $this->createResponse();
    }
    
    /**
     * give the user a JWT iff:
     * 1. they have an account
     * 2. they gave a valid (non-expired) token corresponding to their account
     * 
     * @return \Zend\View\Model\JsonModel
     */
    public function loginWithVerificationTokenAction()
    {
        $identityParam = $this->params()->fromQuery('identity');
        if (! $this->isIdentityValueValid($identityParam)) {
            $this->apiResponse['message'] = "Invalid identity parameter.";
            $this->httpStatusCode = 400;
            return $this->createResponse();
        }
        $userObject = $this->lookupUserObject($identityParam);
        //just check the identity parameter, look them up and send an email
        if (! is_object($userObject) || !is_numeric($userObject->getId())) {
            $this->apiResponse['message'] = "User identity not found.";
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }
        $id = $userObject->getId();
        $userRecord = $this->table->getUser($id);
        $token = $this->params()->fromQuery('token');
        $now = new \DateTime();
        if (! isset($userRecord['verificationToken']) 
            || !isset($token)
            || !isset($userRecord['verificationExpiration'])
            || !$userRecord['verificationExpiration'] instanceof \DateTime
            || $userRecord['verificationExpiration'] < $now 
        ) {
            $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail());
            $this->apiResponse['message'] = "We could not verify the identity, an email has been resent to the user.";
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }
        if ($userRecord['verificationToken'] !== $token) {
            $this->apiResponse['message'] = "Invalid token.";
            $this->httpStatusCode = 401;
            return $this->createResponse();
        }
        
        //delete the token to prevent replay attacks
        try {
            $this->table->updateEntity(
                'user', 
                $id, 
                ['verificationToken' => null,'verificationExpiration' => null]
                );
        } catch (\Exception $e) {
            $this->getLogger()->err(
                'There was a db failure wiping the verification token', 
                ['message' => $e->getMessage()]
                );
        }
        $this->apiResponse = $this->getNewJwtTokenResponse($userObject->getId());
        $this->httpStatusCode = 200;
        return $this->createResponse();
    }
    
    protected function lookupUserObject($identityParam)
    {
        static $fields;
        if (!isset($fields)) {
            $fields = $this->adapter->getOptions()->getAuthIdentityFields();
        }
        $userObject = null;
        while (count($fields) > 0 && !isset($userObject)) {
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
     * @param int $userId
     * @param string $userEmail
     * @return boolean
     */
    protected function createAndSendVerificationEmail($userId, $userEmail)
    {
        try { //there could be db errors here
            $verificationToken = $this->createUserVerificationToken($userId);
        } catch (\Exception $e) {
            $this->getLogger()->crit(
                'JUser: Error recording new verification token in db',
                ['userId' => $userId, 'message' => $e->getMessage()]
                );
            return false;
        }
        $message = $this->createVerificationEmail($verificationToken, $userEmail);
        $this->getLogger()->debug('JUser: About to attempt sending verification email to user', ['userId' => $userId]);
        try {
        $this->mailTransport->send($message);
        } catch (\Exception $e) {
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
     * @param int $userId
     * @return string
     */
    protected function createUserVerificationToken($userId)
    {
        $token = User::generateVerificationToken($this->config['juser']['api_verification_token_length']);
        $expirationInterval = $this->config['juser']['api_verification_token_expiration_interval'];
        $expiration = new \DateTime(null, new \DateTimeZone('UTC'));
        $expiration->add(new \DateInterval($expirationInterval));
        $this->table->updateEntity(
            'user',
            $userId,
            ['verificationToken' => $token, 'verificationExpiration' => $expiration]
        );
        $this->getLogger()->debug('JUser: A new API verification token was recorded in the db', ['userId' => $userId]);
        return $token;
    }
    
    /**
     * Generate an email for the user
     * @param string $token
     * @param string $to
     * @param array $bcc
     * @return \Zend\Mail\Message
     */
    protected function createVerificationEmail($token, $to)
    {
        $messageConfig = $this->config['juser']['verification_email_message'];
        $body = $messageConfig['body'];
        if ($this->getTranslator()) {
            $body = $this->getTranslator()->translate(
                $body, 
                $this->getTranslatorTextDomain(),
                \Locale::getDefault()
                );
        }
        $messageConfig['body'] = sprintf($body, $token);
        $message = \Zend\Mail\MessageFactory::getInstance($messageConfig);
        $message->addTo($to);
        return $message;
    }
    
    /**
     * Creates a new JWT token from the juser config options and returns it in an
     * array format that may be returned as a response
     * @param int $userId
     * @return string[]
     */
    protected function getNewJwtTokenResponse($userId)
    {
        static $options;
        if (! is_array($options)) {
            $config = $this->config['juser'];
            $options = [
                'jwt_id_length' => $config['jwt_id_length'],
                'jwt_id_charlist' => $config['jwt_id_charlist'],
                'jwt_expiration_interval' => $config['jwt_expiration_interval'],
            ];
        }
        $jwtId = Rand::getString($options['jwt_id_length'], $options['jwt_id_charlist']);
        $expiration = new \DateTime(null, new \DateTimeZone('UTC'));
        $expiration->add(new \DateInterval($config['jwt_expiration_interval']));
        $payload = [
            'sub' => $userId,
            'exp' => $expiration->format('U'),
            'jti' => $jwtId,
        ];
        $jwt = $this->generateJwtToken($payload);
        //@todo record the creation in the table
        $this->getLogger()->info('JUser: JWT created', ['userId' => $userId, 'jwtId' => $jwtId]);
        return ['jwt' => $jwt, 'expiration' => $expiration->format('Y-m-d\TH:i:s\Z')];
    }
    
    /**
     * Doesn't look up the user, just checks that it's a possible value
     * @param string $value
     */
    protected function isIdentityValueValid($value)
    {
        /** @var \Zend\InputFilter\Input $identityInput */
        $identityInput = $this->loginFilter->get('identity');
        $identityInput->setValue($value);
        //@todo this shouldn't accept 'je', but it does
        return $identityInput->isValid();
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
    
    public function setApiVerificationRequestNonRegisteredUserEmailHandler(
        $apiVerificationRequestNonRegisteredUserEmailHandler
    ) {
        if (!is_callable($apiVerificationRequestNonRegisteredUserEmailHandler)) {
            throw new \Exception('Value must be callable');
        }
        $this->apiVerificationRequestNonRegisteredUserEmailHandler = 
            $apiVerificationRequestNonRegisteredUserEmailHandler;
        
        return $this;
    }
    
    public function setLoginFilter(InputFilterInterface $loginFilter)
    {
        $this->loginFilter = $loginFilter;
        
        return $this;
    }
}
