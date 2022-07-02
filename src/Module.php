<?php

namespace JUser;

use Laminas\Mvc\MvcEvent;
use Laminas\Db\TableGateway\Feature\GlobalAdapterFeature;
use Laminas\Session\ManagerInterface;

class Module
{
    protected $isMailerWired = false;

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();

        //enable session manager
        $sm->get(ManagerInterface::class);

        //The static adapter is needed for the EditUserForm
        $config = $sm->get('Config');
        if (
            isset($config['lmcuser']['lmcuser_laminas_db_adapter']) &&
            $sm->has($config['lmcuser']['lmcuser_laminas_db_adapter'])
        ) {
            $adapter = $sm->get($config['lmcuser']['lmcuser_laminas_db_adapter']);
            GlobalAdapterFeature::setStaticAdapter($adapter);
        } else {
            throw new \Exception(
                'Please set the [\'lmcuser\'][\'lmcuser_laminas_db_adapter\'] config key for use with the JUser module.'
            );
        }
    }
}
