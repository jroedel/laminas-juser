<?php

namespace JUser\Authentication\Adapter;

use Zend\Authentication\Result as AuthenticationResult;
use Zend\Crypt\Password\Bcrypt;
use Zend\Http\Header\HeaderInterface;
use ZfcUser\Authentication\Adapter\Db;
use Zend\Stdlib\RequestInterface;

class CredentialOrTokenQueryParams extends Db
{
    protected const LOGIN_ACTION_AUTHENTICATE_BY_CREDENTIAL = 'authenticate-by-credential';
    protected const LOGIN_ACTION_AUTHENTICATE_BY_TOKEN = 'authenticate-by-token';

    public const LOGIN_ALLOWED_QUERY_PARAMS = [
        'identity',
        'credential',
        'token'
    ];

    protected $request;

    /**
     * @param \Zend\EventManager\EventInterface $e
     * @return AuthenticationResult
     */
    public function authenticate(\Zend\EventManager\EventInterface $e = null)
    {
        /** @var \Zend\Http\PhpEnvironment\Request $request */
        $request = $this->getRequest();
        //we're only interested in json requests, otherwise
        $contentType = $request->getHeader('Content-Type');
        $isGet = 'GET' === $request->getMethod();
        if (!$isGet
            || !$contentType instanceof HeaderInterface
            || 'application/json' !== $contentType->getFieldValue()
        ) {
            $result = new AuthenticationResult(
                AuthenticationResult::FAILURE_UNCATEGORIZED,
                null,
                ['Please send a GET request of Content-Type application/json.']
            );
            return $result;
        }
        $data = $request->getQuery();
        if (!isset($data['identity']) || !isset($data['credential'])) {
            $message = 'Please provide query parameters: identity and credential.'; //@todo update later
            $result = new AuthenticationResult(
                AuthenticationResult::FAILURE,
                null,
                [$message]
            );
            return $result;
        }
        $identity   = $data['identity'];
        $credential = $data['credential'];
        $credential = $this->preProcessCredential($credential);
        /** @var \ZfcUser\Entity\UserInterface|null $userObject */
        $userObject = null;

        // Cycle through the configured identity sources and test each
        $fields = $this->getOptions()->getAuthIdentityFields();
        while (!is_object($userObject) && count($fields) > 0) {
            $mode = array_shift($fields);
            switch ($mode) {
                case 'username':
                    $userObject = $this->getMapper()->findByUsername($identity);
                    break;
                case 'email':
                    $userObject = $this->getMapper()->findByEmail($identity);
                    break;
            }
        }

        if (!$userObject) {
            $result = new AuthenticationResult(
                AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND,
                null,
                ['A record with the supplied identity could not be found.']
            );
            $this->setSatisfied(false);
            return $result;
        }

        if ($this->getOptions()->getEnableUserState()) {
            // Don't allow user to login if state is not in allowed list
            if (!in_array($userObject->getState(), $this->getOptions()->getAllowedLoginStates())) {
                $result = new AuthenticationResult(
                    AuthenticationResult::FAILURE,
                    null,
                    ['A record with the supplied identity is not active.']
                );
                $this->setSatisfied(false);
                return $result;
            }
        }

        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());
        if (!$bcrypt->verify($credential, $userObject->getPassword())) {
            // Password does not match
            $result = new AuthenticationResult(
                AuthenticationResult::FAILURE_CREDENTIAL_INVALID,
                null,
                ['Supplied credential is invalid.']
            );
            $this->setSatisfied(false);
            return $result;
        }

        // Success!
        // Update user's password hash if the cost parameter has changed
        $this->updateUserPasswordHash($userObject, $credential, $bcrypt);
        $this->setSatisfied(true);

        $result = new AuthenticationResult(
            AuthenticationResult::SUCCESS,
            ['id' => $userObject->getId(), 'username' => $userObject->getUsername()],
            ['Authentication successful.']
        );
        return $result;
    }

    public function getRequest()
    {
        if (!isset($this->request)) {
            $sm = $this->getServiceManager();
            $this->request = $sm->get('Request');
        }
        return $this->request;
    }

    /**
     * Make sure this is a request we can handle.
     * @param RequestInterface $request
     * @return boolean
     */
    public static function isValidLoginRequest($request)
    {
        //make sure verb is GET

        //check that we didn't get extra query params

        //check that we got exactly one of ['token', 'credential']

        //check that all params are strings

        return true;
    }
}
