<?php

namespace JUser\Service;

use JUser\Controller\LoginV1ApiController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;

class LoginV1ApiControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $serviceLocator = $container->getServiceLocator();
        $adapter        = $serviceLocator->get(CredentialOrTokenQueryParams::class);
        $userTable      = $serviceLocator->get(UserTable::class);

        $controller = new LoginV1ApiController($adapter, $userTable);
        return $controller;
    }
}
