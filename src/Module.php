<?php

namespace JUser;

use Laminas\Db\Adapter\Adapter;
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

    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();

        //enable session manager
        $sm->get(ManagerInterface::class);

        //The static adapter is needed for the EditUserForm
        $config = $sm->get('Config');
        $adapterService = isset($config['juser']['db_adapter'])
            ? $config['juser']['db_adapter']
            : Adapter::class;
        if ($sm->has($adapterService)) {
            GlobalAdapterFeature::setStaticAdapter($sm->get($adapterService));
        } else {
            throw new \Exception(
                'Please set the [\'juser\'][\'db_adapter\'] config key for use with the JUser module.'
            );
        }
    }
}
