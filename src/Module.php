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

        //enable session manager, and start it here so that a session which
        //fails validation (see the 'session_manager'.'validators' config) is
        //discarded rather than fataling the request. This was the whole job of
        //the BeaucalInvalidSession module, abandoned and never PHP 8 ready.
        $sessionManager = $sm->get(ManagerInterface::class);
        try {
            $sessionManager->start();
        } catch (\Exception $e) {
            session_unset();
        }

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
