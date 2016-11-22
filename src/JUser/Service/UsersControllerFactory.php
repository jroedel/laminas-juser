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
        $transport      = $serviceLocator->get('JUserMailTransport');
        $message        = $serviceLocator->get('JUserMailMessage');

        $controller = new UsersController();
        $controller->setUserTable($userTable);
        //$controller->setContactForm($form);
        $controller->setMessage($message);
        $controller->setMailTransport($transport);

        return $controller;
    }
}