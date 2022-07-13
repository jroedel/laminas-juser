<?php

declare(strict_types=1);

namespace JUser\Authentication\Adapter;

use Exception;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\Result as AuthenticationResult;
use Laminas\Authentication\Result;
use Laminas\Filter\FilterInterface;
use Laminas\Log\LoggerInterface;
use Laminas\Stdlib\RequestInterface;
use Laminas\Validator\ValidatorInterface;
use LmcUser\Mapper\UserInterface as UserMapperInterface;
use LmcUser\Options\ModuleOptions;

use function in_array;
use function is_object;
use function property_exists;

/**
 * The plan here is to copy over code from ApiController to check the validity of the JWT.
 *
 * Then we simply accept the sub parameter of the jwt payload as the current userId.  We probably
 * have to query the database for the whole userObject
 *
 * It might be better to extend the Db class directly instead of trying to slim it down.
 */
class Jwt implements AdapterInterface
{
    /** @var callable */
    protected $credentialPreprocessor;

    public function __construct(
        private UserMapperInterface $mapper,
        private ModuleOptions $options,
        private RequestInterface $request,
        private LoggerInterface $logger,
        private ValidatorInterface $authorizationHeaderValidator,
        private FilterInterface $authorizationHeaderFilter,
        private array $config
    ) {
    }

    public function authenticate(): Result
    {
        //check if we've already authenticated the user
//         $e = $e->getTarget();
//         if ($this->isSatisfied()) {
//             $storage = $this->getStorage()->read();
//             $e->setIdentity($storage['identity'])
//               ->setCode(AuthenticationResult::SUCCESS)
//               ->setMessages(array('Authentication successful.'));
//             return;
//         }

        $request = $this->request;
//         $response = $event->getResponse();
//         $isAuthorizationRequired = $event->getRouteMatch()->getParam('isAuthorizationRequired');
        $config = $this->config;

        if (
            isset($config['ApiRequest']) && isset($config['ApiRequest']['jwtAuth'])
            && isset($config['ApiRequest']['jwtAuth']['cypherKey'])
            && isset($config['ApiRequest']['jwtAuth']['tokenAlgorithm'])
        ) {
            $cypherKey      = $config['ApiRequest']['jwtAuth']['cypherKey'];
            $tokenAlgorithm = $config['ApiRequest']['jwtAuth']['tokenAlgorithm'];
            $jwtToken       = $this->findJwtToken($request);
            if ($jwtToken) {
                $payload = $this->decodeJwtToken($jwtToken, $cypherKey, $tokenAlgorithm);
                if (is_object($payload)) {
                    //now we have a validly signed JWT
                    if (! property_exists($payload, 'sub')) {
                        //failure, all JWTs should contain a sub with the userId
                        return new Result(
                            AuthenticationResult::FAILURE_IDENTITY_AMBIGUOUS,
                            [],
                            ['No identity supplied.']
                        );
                    }
                    $userId     = $payload->sub;
                    $userObject = $this->getMapper()->findById($userId);

                    if (! $userObject) {
                        return new Result(
                            AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND,
                            [],
                            ['A record with the supplied identity could not be found.']
                        );
                    }
                    //validate that the user specified is activated

                    if ($this->getOptions()->getEnableUserState()) {
                        // Don't allow user to login if state is not in allowed list
                        if (! in_array($userObject->getState(), $this->getOptions()->getAllowedLoginStates())) {
                            return new Result(
                                AuthenticationResult::FAILURE_UNCATEGORIZED,
                                [],
                                ['A record with the supplied identity is not active.']
                            );
                        }
                    }
                    //success, we found a validly signed JWT

                    // regen the id
//                     $session = new SessionContainer($this->getStorage()->getNameSpace());
//                     $session->getManager()->regenerateId();

                    // Success!
//                     $storage = $this->getStorage()->read();
//                     $storage['identity'] = $e->getIdentity();
//                     $this->getStorage()->write($storage);
                    return new Result(
                        AuthenticationResult::SUCCESS,
                        $userObject,
                        ['Authentication successful.']
                    );
                }
                //failure, invalid JWT or signature
                return new Result(
                    AuthenticationResult::FAILURE_CREDENTIAL_INVALID,
                    [],
                    ['Supplied credential is invalid.']
                );
            } else {
                //failure, we couldn't find the JWT.  it probably wasn't included
                return new Result(
                    AuthenticationResult::FAILURE_UNCATEGORIZED,
                    [],
                    ['No valid JWT found.']
                );
            }
        } else {
            //failure, the application isn't configured to receive JWT's. We should throw an exception
            throw new Exception("Please disable JWT auth or configure ['ApiRequest']['jwtAuth']");
        }
    }

    /**
     * Check Request object have Authorization token or not
     */
    public function findJwtToken(RequestInterface $request): string|null
    {
        $jwtToken = $request->getHeaders("Authorization")
            ? $request->getHeaders("Authorization")->getFieldValue()
            : null;
        if ($jwtToken && $this->authorizationHeaderValidator->isValid($jwtToken)) {
//            $this->logger->info('We found a JWT: '.$this->authorizationHeaderFilter->filter($jwtToken));
            return $this->authorizationHeaderFilter->filter($jwtToken);
        }
        if ($request->isGet()) {
            //@todo add some validation
            return $request->getQuery('token');
        }
        if ($request->isPost()) {
            //@todo add some validation
             return $request->getPost('token');
        }
        return null;
    }

    /**
     * contain encoded token for user.
     */
    protected function decodeJwtToken(string $token, $cypherKey, $tokenAlgorithm): object|null
    {
        try {
            $decodeToken = \Firebase\JWT\JWT::decode($token, $cypherKey, [$tokenAlgorithm]);
        } catch (Exception $e) {
            //@todo log
            return null;
        }
        return $decodeToken;
    }

    public function getOptions(): ModuleOptions
    {
        return $this->options;
    }

    public function getMapper(): UserMapperInterface
    {
        return $this->mapper;
    }
}
