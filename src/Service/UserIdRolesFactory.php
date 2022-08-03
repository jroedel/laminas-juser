<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Provider\Role\UserIdRoles;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\EntitiesService;

class UserIdRolesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var EntitiesService $entitiesService */
        $entitiesService    = $container->get(EntitiesService::class);
        $userRoleEntitySpec = $entitiesService->getEntity('user');
        $tableGateway       = new TableGateway(
            $userRoleEntitySpec->tableName,
            $container->get('lmcuser_laminas_db_adapter')
        );
        return new UserIdRoles($tableGateway, $userRoleEntitySpec->tableName);
    }
}
