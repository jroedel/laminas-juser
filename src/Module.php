<?php
namespace JUser;

use Zend\Mvc\MvcEvent;
use Zend\Db\TableGateway\Feature\GlobalAdapterFeature;
use Zend\Session\ManagerInterface;
use JUser\Model\UserTable;
use Zend\Math\Rand;
use ZfcUser\Service\User;
use JUser\Service\Mailer;
class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
    
    public function onBootstrap(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();
        
        //enable session manager
        $manager = $sm->get(ManagerInterface::class);
        
        //The static adapter is needed for the EditUserForm
        $config = $sm->get('Config');
        if (isset($config['zfcuser']['zend_db_adapter']) &&
            $sm->has($config['zfcuser']['zend_db_adapter'])
        ) {
            $adapter = $sm->get($config['zfcuser']['zend_db_adapter']);
            GlobalAdapterFeature::setStaticAdapter($adapter);
        } else {
            throw new \Exception('Please set the [\'zfcuser\'][\'zend_db_adapter\'] config key for use with the JUser module.');
        }
                
        /** @var User $userService */
        $userService = $sm->get('zfcuser_user_service');
        
        //the mailer will listen on zfcUser events to dispatch relevant emails
        $listener = $sm->get(Mailer::class);
        $listener->attach($userService->getEventManager());
    }
}
