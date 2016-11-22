<?php
namespace JUser\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JUser\Model\UserTable;
use Zend\Db\TableGateway\TableGateway;

class UserTableFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');
        $userGateway = new TableGateway('user', $dbAdapter);
        $userRoleGateway = new TableGateway('user_role_linker', $dbAdapter);
        $table = new UserTable($userGateway, $userRoleGateway, $user);
        return $table;
    }
}