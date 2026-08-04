<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LoginTokenServiceFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        $juserConfig = isset($config['juser']) ? $config['juser'] : [];

        $service = new LoginTokenService($container->get(UserTable::class), $juserConfig);

        if ($container->has('JUser\Logger')) {
            $service->setLogger($container->get('JUser\Logger'));
        }

        return $service;
    }
}
