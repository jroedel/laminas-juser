<?php

declare(strict_types=1);

namespace JUser;

use Laminas\Db\TableGateway\Feature\GlobalAdapterFeature;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\ManagerInterface;
use Webmozart\Assert\Assert;

use const PHP_SAPI;

class Module
{
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $sm  = $app->getServiceManager();

        //enable session manager
        if (PHP_SAPI !== 'cli') {
            //don't worry about sessions if we're testing
            $sm->get(ManagerInterface::class);
        }

        //The static adapter is needed for the EditUserForm
        $config = $sm->get('Config');
        Assert::keyExists($config, 'lmcuser');
        Assert::keyExists($config['lmcuser'], 'lmcuser_laminas_db_adapter');
        Assert::true($sm->has($config['lmcuser']['lmcuser_laminas_db_adapter']));
        $adapter = $sm->get($config['lmcuser']['lmcuser_laminas_db_adapter']);

        //@todo factor this out
        GlobalAdapterFeature::setStaticAdapter($adapter);
    }
}
