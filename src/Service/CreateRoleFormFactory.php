<?php

namespace JUser\Service;

use Psr\Container\ContainerInterface;
use JUser\Model\UserTable;
use JUser\Form\CreateRoleForm;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class CreateRoleFormFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var UserTable $userTable **/
        $userTable = $container->get(UserTable::class);
        $form = new CreateRoleForm(DbAdapterResolver::fromContainer($container));
        $roles = $userTable->getRolesValueOptions();
        $form->get('parentId')->setValueOptions($roles);

        return $form;
    }
}
