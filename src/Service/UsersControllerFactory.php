<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Controller\UsersController;
use JUser\Form\CreateRoleForm;
use JUser\Form\EditUserForm;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function array_key_exists;

class UsersControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $userTable = $container->get(UserTable::class);

        //@todo make a new constructor
        $controller = new UsersController();
        $controller->setUserTable($userTable);

        $config = $container->get('JUser\Config');

        $services                 = [];
        $services['JUser\Config'] = $config;
        if (array_key_exists('person_provider', $config)) {
            $personProvider = $config['person_provider'];
            if ($container->has($personProvider)) {
                $services[$personProvider] = $container->get($personProvider);
            }
        }

        $services[EditUserForm::class]      = $container->get(EditUserForm::class);
        $services[CreateRoleForm::class]    = $container->get(CreateRoleForm::class);
        $services['lmcuser_module_options'] = $container->get('lmcuser_module_options');
        $services[UserTable::class]         = $userTable;
        $services[Mailer::class]            = $container->get(Mailer::class);
        $logger = $container->get('JUser\Logger');
        $controller->setLogger($logger);

        $controller->setServices($services);

        return $controller;
    }
}
