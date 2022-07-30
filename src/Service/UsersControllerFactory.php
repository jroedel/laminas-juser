<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Controller\UsersController;
use JUser\Form\ChangeOtherPasswordForm;
use JUser\Form\CreateRoleForm;
use JUser\Form\EditUserForm;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webmozart\Assert\Assert;

class UsersControllerFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return UsersController
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('JUser\Config');
        Assert::keyExists($config, 'person_provider');
        Assert::true($container->has($config['person_provider']));
        $personProvider          = $container->get($config['person_provider']);
        $userTable               = $container->get(UserTable::class);
        $editUserForm            = $container->get(EditUserForm::class);
        $createRoleForm          = $container->get(CreateRoleForm::class);
        $changeOtherPasswordForm = $container->get(ChangeOtherPasswordForm::class);
        $lmcModuleOptions        = $container->get('lmcuser_module_options');
        $mailer                  = $container->get(Mailer::class);
        $logger                  = $container->get('JUser\Logger');

        return new UsersController(
            userTable: $userTable,
            logger: $logger,
            lmcModuleOptions: $lmcModuleOptions,
            mailer: $mailer,
            editUserForm: $editUserForm,
            createRoleForm: $createRoleForm,
            changeOtherPasswordForm: $changeOtherPasswordForm,
            personProvider: $personProvider,
        );
    }
}
