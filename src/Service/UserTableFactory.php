<?php
namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class UserTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $authService = $container->get('zfcuser_auth_service');
        $user = $authService->getIdentity();
		
        $dbAdapter = $container->get(Adapter::class);
        $userGateway = new TableGateway('user', $dbAdapter);
        $userRoleGateway = new TableGateway('user_role_linker', $dbAdapter);
        $table = new UserTable($userGateway, $userRoleGateway, $user);
        return $table;
    }
}