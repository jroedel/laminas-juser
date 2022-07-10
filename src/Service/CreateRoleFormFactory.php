<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Form\CreateRoleForm;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CreateRoleFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var UserTable $userTable **/
        $userTable = $container->get(UserTable::class);
        $form      = new CreateRoleForm();
        $roles     = $userTable->getRolesValueOptions();
        $form->get('parentId')->setValueOptions($roles);

        return $form;
    }
}
