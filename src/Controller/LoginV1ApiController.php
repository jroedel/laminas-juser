<?php
namespace JUser\Controller;

use RestApi\Controller\ApiController;
use Zend\Validator\EmailAddress;
use Zend\Math\Rand;
use Carbon\Carbon;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use JUser\Model\UserTable;

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

    public function __construct(CredentialOrTokenQueryParams $adapter, UserTable $table)
    {
        $this->adapter = $adapter;
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
            if (!$authResult->isValid()) {
                if (\Zend\Authentication\Result::FAILURE_UNCATEGORIZED === $authResult->getCode()) {
                    $this->httpStatusCode = 400;
                } else {
                    $this->httpStatusCode = 401;
                }
                $this->apiResponse['message'] = $authResult->getMessages()[0];
                return $this->createResponse();
            }
            $jwtId = Rand::getString(10);
            $expiration = Carbon::now()
            ->addMonths(6);
            $payload = [
                'sub' => $authResult->getIdentity()['id'],
                'exp' => $expiration->format('U'), //'Y-m-d\TH:i:s\Z'),
                'jti' => $jwtId,
            ];
            $jwt = $this->generateJwtToken($payload);
            //@todo log the creation of this JWT
            //@todo we should register the JWT id in the database just for auditing.
            $this->httpStatusCode = 200;
            $this->apiResponse = ['jwt' => $jwt, 'expiration' => $expiration->format('Y-m-d\TH:i:s\Z')];
            return $this->createResponse();
        }
    }

    /**
     * Check if a string is an email address
     * @param string $text
     * @return boolean
     */
    protected static function isEmailAddress($text)
    {
        static $validator;
        if (!isset($validator)) {
            $validator = new EmailAddress();
        }
        return $validator->isValid($text);
    }
}
