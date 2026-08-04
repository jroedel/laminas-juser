<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Controller\LoginController;
use JUser\Model\UserTable;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\ManagerInterface as SessionManagerInterface;

class LoginControllerFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        $juserConfig = isset($config['juser']) ? $config['juser'] : [];

        $controller = new LoginController(
            $container->get('JUser\AuthService'),
            $container->get(UserTable::class),
            $container->get(LoginTokenService::class),
            $container->get(Mailer::class),
            $container->get(RouteStackInterface::class),
            $container->get(SessionManagerInterface::class),
            $juserConfig
        );

        if ($container->has('JUser\Logger')) {
            $controller->setLogger($container->get('JUser\Logger'));
        }

        return $controller;
    }
}
