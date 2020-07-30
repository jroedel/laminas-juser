<?php

namespace JUser\Service;

use JUser\Controller\LoginV1ApiController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;

class LoginV1ApiControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $serviceLocator = $container->getServiceLocator();
        $adapter        = $serviceLocator->get(CredentialOrTokenQueryParams::class);
        $userTable      = $serviceLocator->get(UserTable::class);
        $mailTransport  = $serviceLocator->get(\Zend\Mail\Transport\TransportInterface::class);
        $config         = $serviceLocator->get('Config');

        $controller = new LoginV1ApiController($adapter, $userTable, $mailTransport, $config);
        
        $logger = $container->get('JUser\Logger');
        $controller->setLogger($logger);
        
        $loginFilter = $container->get(\ZfcUser\Form\LoginFilter::class);
        $controller->setLoginFilter($loginFilter);
        
        if (isset($config['juser']['api_verification_request_non_registered_user_email_handler'])) {
            if (is_callable($config['juser']['api_verification_request_non_registered_user_email_handler'])) {
                $controller->setApiVerificationRequestNonRegisteredUserEmailHandler(
                    $config['juser']['api_verification_request_non_registered_user_email_handler']
                    );
            } elseif ($container->has(
                $config['juser']['api_verification_request_non_registered_user_email_handler']
                )
            ) {
                $apiVerificationRequestNonRegisteredUserEmailHandler = 
                    $container->get($config['juser']['api_verification_request_non_registered_user_email_handler']);
                if (is_callable($apiVerificationRequestNonRegisteredUserEmailHandler)) {
                    $controller->setApiVerificationRequestNonRegisteredUserEmailHandler(
                        $apiVerificationRequestNonRegisteredUserEmailHandler
                        );
                }
            } else {
                try {
                    $apiVerificationRequestNonRegisteredUserEmailHandler 
                        = new $config['juser']['api_verification_request_non_registered_user_email_handler']();
                    if (is_callable($apiVerificationRequestNonRegisteredUserEmailHandler)) {
                        $controller->setApiVerificationRequestNonRegisteredUserEmailHandler(
                            $apiVerificationRequestNonRegisteredUserEmailHandler
                            );
                    }
                } catch (\Exception $e) {}
            }
        }
        
        return $controller;
    }
}
