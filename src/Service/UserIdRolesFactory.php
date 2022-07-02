<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use JUser\Provider\Role\UserIdRoles;

class UserIdRolesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /* @var $tableGateway \Laminas\Db\TableGateway\TableGateway */
        $tableGateway = new TableGateway('users', $container->get('lmcuser_laminas_db_adapter'));
        /* @var $userService \LmcUser\Service\User */

        $provider = new UserIdRoles($tableGateway);

        return $provider;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, UserIdRoles::class);
    }
}
