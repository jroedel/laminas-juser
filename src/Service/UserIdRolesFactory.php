<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Provider\Role\UserIdRoles;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserIdRolesFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $adapterService = isset($config['juser']['db_adapter'])
            ? $config['juser']['db_adapter']
            : Adapter::class;
        $tableGateway = new TableGateway('users', $container->get($adapterService));

        return new UserIdRoles($tableGateway);
    }
}
