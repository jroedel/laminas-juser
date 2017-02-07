<?php
namespace JUser\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Patres\Form\EditCourseForm;
use JUser\Form\EditUserForm;
use JUser\Model\UserTable;
use JUser\Model\PersonValueOptionsProviderInterface;

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
		$config = $serviceLocator->get('JUser\Config');
		$personProvider = $config['person_provider'];
        if ($serviceLocator->has ($personProvider)) {
            /** @var PersonValueOptionsProviderInterface $provider **/
        	$provider = $serviceLocator->get($personProvider);
            if (!$provider instanceof PersonValueOptionsProviderInterface) {
        	   throw new \InvalidArgumentException('`person_provider` specified in the JUser config does not implement the PersonValueOptionsProviderInterface.');
            }
            $persons = $provider->getPersonValueOptions();
        	$form->setPersonValueOptions($persons);
        } else {
            throw new \InvalidArgumentException('`person_provider` specified in the JUser config does not exist.');
        }
		$roles = $userTable->getRolesValueOptions();
		$form->get('roles')->setValueOptions($roles);

		return $form;
    }
}
