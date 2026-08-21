<?php

namespace JUser\Bridge\Laminas;

use Interop\Container\ContainerInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of instantiating {@see ZfcUserZendDbPlusSelfAsRole}
 */
class ZfcUserZendDbPlusSelfAsRoleFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        $adapterService = isset($config['juser']['db_adapter'])
            ? $config['juser']['db_adapter']
            : Adapter::class;
        $tableGateway = new TableGateway('user_role_linker', $container->get($adapterService));
        $authService = $container->get('JUser\AuthService');
        $bjyConfig = $container->get('BjyAuthorize\Config');

        $provider = new ZfcUserZendDbPlusSelfAsRole($tableGateway, $authService);

        $provider->setDefaultRole($bjyConfig['default_role']);

        return $provider;
    }
}
