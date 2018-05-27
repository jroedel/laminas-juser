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

        return $controller;
    }
}