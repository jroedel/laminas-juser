<?php
namespace JUser\Service;

use JUser\Controller\UsersController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;

class UsersControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $serviceLocator = $container->getServiceLocator();
        $userTable      = $serviceLocator->get(UserTable::class);

        $controller = new UsersController();
        $controller->setUserTable($userTable);

        $config = $serviceLocator->get('JUser\Config');

        $services = [];
        $services['JUser\Config'] = $config;
        if (key_exists('person_provider', $config)) {
            $personProvider = $config['person_provider'];
            if ($serviceLocator->has($personProvider)) {
                $services[$personProvider] = $serviceLocator->get($personProvider);
            }
        }

        $services[\JUser\Form\EditUserForm::class] = $serviceLocator->get(\JUser\Form\EditUserForm::class);
        $services[\JUser\Form\CreateRoleForm::class] = $serviceLocator->get(\JUser\Form\CreateRoleForm::class);
        $services['zfcuser_module_options'] = $serviceLocator->get('zfcuser_module_options');
        $services[UserTable::class] = $userTable;
        $services[Mailer::class] = $serviceLocator->get(Mailer::class);

        $controller->setServices($services);

        return $controller;
    }
}
