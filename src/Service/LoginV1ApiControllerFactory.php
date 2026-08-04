<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Controller\LoginV1ApiController;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LoginV1ApiControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $controller = new LoginV1ApiController(
            $container->get(UserTable::class),
            $container->get(LoginTokenService::class),
            $container->get(Mailer::class),
            $container->get('Config')
        );

        if ($container->has('JUser\Logger')) {
            $controller->setLogger($container->get('JUser\Logger'));
        }

        return $controller;
    }
}
