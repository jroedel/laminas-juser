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
        /** @var UserTable $userTable **/
		$userTable = $serviceLocator->get ( 'JUser\Model\UserTable' );
		$form = new EditUserForm();
        if ($serviceLocator->has ( 'Patres\Model\PatresTable' )) {
            /** @var \Patres\Model\PatresTable $patresTable **/
        	$patresTable = $serviceLocator->get ( 'Patres\Model\PatresTable' );
        	$persons = $patresTable->getPersonValueOptions(true, false);
        	$form->get('personId')->setValueOptions($persons);
        }
		$roles = $userTable->getRolesValueOptions();
		$form->get('roles')->setValueOptions($roles);

		return $form;
    }
}
