<?php

namespace JUser\Service;

use Psr\Container\ContainerInterface;
use JUser\Model\UserTable;

class LoginTokenServiceFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
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
