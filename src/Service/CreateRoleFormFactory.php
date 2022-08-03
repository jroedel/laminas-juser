<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Form\CreateRoleForm;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\EntitiesService;

class CreateRoleFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var EntitiesService $entitiesService */
        $entitiesService    = $container->get(EntitiesService::class);
        $userRoleEntitySpec = $entitiesService->getEntity('user-role');
        $adapter            = $container->get('lmcuser_laminas_db_adapter');
        /** @var UserTable $userTable **/
        $userTable         = $container->get(UserTable::class);
        $rolesValueOptions = $userTable->getRolesValueOptions();

        return new CreateRoleForm($adapter, $userRoleEntitySpec, $rolesValueOptions);
    }
}
