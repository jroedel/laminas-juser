<?php
namespace JUser\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JUser\Model\UserTable;
use JUser\Form\CreateRoleForm;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class CreateRoleFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return EditCourseForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var UserTable $userTable **/
		$userTable = $serviceLocator->get ( 'JUser\Model\UserTable' );
		$form = new CreateRoleForm();
		$roles = $userTable->getRolesValueOptions();
		$form->get('parentId')->setValueOptions($roles);
		
		return $form;
    }
}
