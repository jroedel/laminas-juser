<?php

namespace JUser\Controller;

use RestApi\Controller\ApiController;
use Zend\Validator\EmailAddress;
use Zend\Math\Rand;
use Carbon\Carbon;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use JUser\Model\UserTable;
use Zend\Mail\Transport\TransportInterface;
use JUser\Model\User;

class LoginV1ApiController extends ApiController
{
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

    /**
     * Should always be GET
     * There are 3 query parameters we look at:
     * 1. identity (required)
     * 2. credential - the user password. Not every user will have a password
     * 3. token - a verification token sent to email. There's only one valid user token at a time
     *
     * There are 3 main cases handled here:
     * A. They just give just us an identity param - we check if the account exists
     *      If not, AND the email is in the person table, a new account will be created.
     *      If everything checks out, we send an email (out-of-band verification) with a token.
     *      We return a 202 code if the email was sent. Otherwise, we'll send a ...@todo finish
     * B. They give us an 'identity' and 'token' query param. We check the user table to see if
     *      it matches AND is still valid. If so, we return a JSON response with a JWT
     * C. They send us an 'identity' and 'credential'. We check if they match, and return a JWT.
     */
    public function loginAction()
    {
        //make sure verb is GET
        if ('GET' !== $this->getRequest()->getMethod()) {
            $this->httpStatusCode = 400;
            $this->apiResponse['message'] = 'This method only accepts GET requests.';
            return $this->createResponse();
        }

        $queryParams = $this->params()->fromQuery();
        if (count($queryParams) === 1 && isset($queryParams['identity'])) { //we handle this
            //@todo
            $this->httpStatusCode = 503;
            $this->apiResponse['message'] = 'We haven\'t yet finished developing emailed verification tokens.';
            return $this->createResponse();
        } else { //we pass it on to the authenticator
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
            $jwtResponse = $this->getNewJwtToken($authResult->getIdentity()['id']);
            //@todo log the creation of this JWT
            //@todo we should register the JWT id in the database just for auditing.
            $this->httpStatusCode = 200;
            $this->apiResponse = $jwtResponse;
            return $this->createResponse();
        }
    }
    
    public function requestVerificationTokenAction()
    {
        $identityParam = $this->params()->fromQuery('identity');
        $userObject = $this->lookupUserObject($identityParam);
        //just check the identity parameter, look them up and send an email
        if ($userObject) {
            $this->createAndSendVerificationEmail($userObject->getId(), $userObject->getEmail());
        } elseif ($this->isEmailAddress($identityParam)) {
            //here we should allow the package user to send us a function to see if we allow a new account to create
            /*
             * @todo Allow for consuming package to decide if an email address should be allowed to make an account
             * How do we do this? a Validator to which we pass an email address and they just respond TRUE or FALSE
             * * The user sets a config with a service.
             * Should new users be created automatically when api/v1/users/request-verification-token
             * allow_auto_account_creation_through_api=bool
             * Allows the user to allow or deny the creation of an account according to email address:
             * auto_account_creation_through_api_email_validator
             */
            
        } else {
            //there's nothing we can do here. Just error out
        }
        $this->httpStatusCode = 200;
        $this->apiResponse['message'] = 'Hang in there champ, you\'ll be getttin that email.';
        return $this->createResponse();
    }
    
    public function loginWithVerificationTokenAction()
    {
        /*
         * give the user a JWT iff:
         * 1. they have an account
         * 2. they gave a valid (non-expired) token corresponding to their account
         */
        $identityParam = $this->params()->fromQuery('identity');
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
    
    protected function createAndSendVerificationEmail($userId, $userEmail)
    {
        $verificationToken = $this->createUserVerificationToken($userId);
        $message = $this->createVerificationEmail($verificationToken, $userEmail);
//         var_dump('were sending email to '.$userEmail);
        $this->mailTransport->send($message);
//         var_dump('weve presumably sent the message');
    }
    
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
        //@todo log this
        return $token;
    }
    
    protected function createVerificationEmail($token, $to, $bcc = [])
    {
        $messageConfig = $this->config['juser']['verification_email_message'];
        $messageConfig['body'] = sprintf($messageConfig['body'], $token);
        $message = \Zend\Mail\MessageFactory::getInstance($messageConfig);
        $message->addTo($to)->addBcc($bcc);
        return $message;
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
}
