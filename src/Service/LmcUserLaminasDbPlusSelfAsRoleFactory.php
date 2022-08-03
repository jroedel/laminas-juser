<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Provider\Identity\LmcUserZendDbPlusSelfAsRole;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Service\EntitiesService;

class LmcUserLaminasDbPlusSelfAsRoleFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var EntitiesService $entitiesService */
        $entitiesService        = $container->get(EntitiesService::class);
        $userRoleLinkEntitySpec = $entitiesService->getEntity('user-role-link');
        $tableGateway           = new TableGateway(
            $userRoleLinkEntitySpec->tableName,
            $container->get('lmcuser_laminas_db_adapter')
        );
        /** @var User $userService */
        $userService = $container->get('lmcuser_user_service');
        $config      = $container->get('BjyAuthorize\Config');

        $provider = new LmcUserZendDbPlusSelfAsRole($tableGateway, $userService);

        $provider->setDefaultRole($config['default_role']);

        return $provider;
    }
}
