<?php

declare(strict_types=1);

namespace JUser\Service;

use Psr\Container\ContainerInterface;
use JUser\Provider\Identity\LmcUserZendDbPlusSelfAsRole;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use LmcUser\Service\User;

class LmcUserLaminasDbPlusSelfAsRoleFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $tableGateway = new TableGateway('user_role_linker', $container->get('lmcuser_laminas_db_adapter'));
        /** @var User $userService */
        $userService = $container->get('lmcuser_user_service');
        $config      = $container->get('BjyAuthorize\Config');

        $provider = new LmcUserZendDbPlusSelfAsRole($tableGateway, $userService);

        $provider->setDefaultRole($config['default_role']);

        return $provider;
    }

    public function createService(ServiceLocatorInterface $serviceLocator): object
    {
        return $this($serviceLocator, LmcUserZendDbPlusSelfAsRole::class);
    }
}
