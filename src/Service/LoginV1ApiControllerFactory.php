<?php

declare(strict_types=1);

namespace JUser\Service;

use Exception;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use JUser\Controller\LoginV1ApiController;
use JUser\Model\UserTable;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Form\LoginFilter;
use Psr\Container\ContainerInterface;

use function is_callable;

class LoginV1ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $adapter       = $container->get(CredentialOrTokenQueryParams::class);
        $userTable     = $container->get(UserTable::class);
        $mailTransport = $container->get(TransportInterface::class);
        $config        = $container->get('Config');

        $controller = new LoginV1ApiController($adapter, $userTable, $mailTransport, $config);

        $logger = $container->get('JUser\Logger');
        $controller->setLogger($logger);

        $loginFilter = $container->get(LoginFilter::class);
        $controller->setLoginFilter($loginFilter);

        $translator = $container->get('jtranslate_translator');
        $controller->setTranslator($translator, 'JUser');

        if (isset($config['juser']['api_verification_request_non_registered_user_email_handler'])) {
            if (is_callable($config['juser']['api_verification_request_non_registered_user_email_handler'])) {
                $controller->setApiVerificationRequestNonRegisteredUserEmailHandler(
                    $config['juser']['api_verification_request_non_registered_user_email_handler']
                );
            } elseif (
                $container->has(
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
                } catch (Exception) {
                }
            }
        }

        return $controller;
    }
}
