<?php

namespace JUser\Service;

use JUser\Controller\UsersController;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use SionModel\Service\ActingUserProviderInterface;

class UsersControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $userTable      = $container->get(UserTable::class);

        $controller = new UsersController();
        $controller->setUserTable($userTable);

        $config = $container->get('JUser\Config');

        $services = [];
        $services['JUser\Config'] = $config;
        if (key_exists('person_provider', $config)) {
            $personProvider = $config['person_provider'];
            if ($container->has($personProvider)) {
                $services[$personProvider] = $container->get($personProvider);
            }
        }

        $services[\JUser\Form\EditUserForm::class] = $container->get(\JUser\Form\EditUserForm::class);
        $services[\JUser\Form\CreateRoleForm::class] = $container->get(\JUser\Form\CreateRoleForm::class);
        $services[UserTable::class] = $userTable;
        //DeleteUserForm is built per call rather than fetched as a service — a form
        //holds the data and the messages of whatever was last validated through it,
        //and the ServiceManager shares by default — so the controller needs the
        //adapter the form's RecordExists validator wants.
        $services[Adapter::class] = DbAdapterResolver::fromContainer($container);
        $services[Mailer::class] = $container->get(Mailer::class);
        //Both are cheap to build — config plus the db adapter, no request, no
        //identity — which is what makes eager construction safe here. A
        //request-dependent service in this list would 500 every action of this
        //controller, not just the one that needs it.
        $services[ApiTokenService::class] = $container->get(ApiTokenService::class);
        if ($container->has(ActingUserProviderInterface::class)) {
            $services[ActingUserProviderInterface::class] = $container->get(ActingUserProviderInterface::class);
        }
        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $controller->setLogger($logger);
        }

        $controller->setServices($services);

        return $controller;
    }
}
