<?php

namespace JUser\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use SionModel\Service\ActingUserProviderInterface;

class UserTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);
        $actingUserProvider = $container->get(ActingUserProviderInterface::class);
        $table = new UserTable($dbAdapter, $container, $actingUserProvider);

        $cache = $container->get('JUser\Cache');
        $em = $container->get('Application')->getEventManager();
        $table->setPersistentCache($cache);
        $table->wireOnFinishTrigger($em);

        $mailer = $container->get(Mailer::class);
        $table->setMailer($mailer);

        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $table->setLogger($logger);
        }

        return $table;
    }
}
