<?php
namespace JUser\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Patres\Form\EditCourseForm;
use JUser\Form\EditUserForm;
use JUser\Model\UserTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class EditUserFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return EditCourseForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var \Patres\Model\PatresTable $patresTable **/
		$patresTable = $serviceLocator->get ( 'Patres\Model\PatresTable' );
        /** @var UserTable $userTable **/
		$userTable = $serviceLocator->get ( 'JUser\Model\UserTable' );
		$form = new EditUserForm();
		$roles = $userTable->getRolesValueOptions();
		$persons = $patresTable->getPersonValueOptions(true, false);
		$form->get('roles')->setValueOptions($roles);
		$form->get('personId')->setValueOptions($persons);
		
		return $form;
    }
}
