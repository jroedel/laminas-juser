<?php
namespace JUser\Service;

use JUser\Controller\UsersController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JUser\Model\UserTable;

class UsersControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $services)
    {
        $serviceLocator = $services->getServiceLocator();
        $userTable      = $serviceLocator->get('JUser\Model\UserTable');

        $controller = new UsersController();
        $controller->setUserTable($userTable);

        return $controller;
    }
}