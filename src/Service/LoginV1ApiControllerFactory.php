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
use Webmozart\Assert\Assert;

use function is_callable;

class LoginV1ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $adapter       = $container->get(CredentialOrTokenQueryParams::class);
        $userTable     = $container->get(UserTable::class);
        $mailTransport = $container->get(TransportInterface::class);
        $config        = $container->get('Config');
        $logger        = $container->get('JUser\Logger');
        $loginFilter   = $container->get(LoginFilter::class);
        $translator    = $container->get('jtranslate_translator');

        Assert::keyExists($config['juser'], 'api_verification_request_non_registered_user_email_handler');
        $apiVerificationRequestNonRegisteredUserEmailHandler = null;
        if (is_callable($config['juser']['api_verification_request_non_registered_user_email_handler'])) {
            $apiVerificationRequestNonRegisteredUserEmailHandler =
                $config['juser']['api_verification_request_non_registered_user_email_handler'];
        } elseif (
            $container->has(
                $config['juser']['api_verification_request_non_registered_user_email_handler']
            )
        ) {
            $apiVerificationRequestNonRegisteredUserEmailHandler =
                $container->get($config['juser']['api_verification_request_non_registered_user_email_handler']);
        } else {
            try {
                $apiVerificationRequestNonRegisteredUserEmailHandler
                    = new $config['juser']['api_verification_request_non_registered_user_email_handler']();
            } catch (Exception) {
            }
        }
        Assert::isCallable($apiVerificationRequestNonRegisteredUserEmailHandler);

        return new LoginV1ApiController(
            adapter: $adapter,
            table: $userTable,
            mailTransport: $mailTransport,
            logger: $logger,
            translator: $translator,
            loginFilter: $loginFilter,
            apiVerificationRequestNonRegisteredUserEmailHandler: $apiVerificationRequestNonRegisteredUserEmailHandler,
            config: $config
        );
    }
}
