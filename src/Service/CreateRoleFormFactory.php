<?php
namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use JUser\Form\CreateRoleForm;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class CreateRoleFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var UserTable $userTable **/
        $userTable = $container->get(UserTable::class);
        $form = new CreateRoleForm();
        $roles = $userTable->getRolesValueOptions();
        $form->get('parentId')->setValueOptions($roles);
        
        return $form;
    }
}
