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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);
        $actingUserProvider = $container->get(ActingUserProviderInterface::class);
        $table = new UserTable($dbAdapter, $container, $actingUserProvider);

        //JUser keeps its own APCu namespace and TTL (juser.cache_options), so it
        //replaces the application-wide SionModel\PersistentCache that SionTable's
        //constructor injected. Replacing the storage discards the dependency map
        //that came with it — see SionCacheTrait::setPersistentCache().
        //
        //No wireOnFinishTrigger() here: the constructor already attached it, and
        //attaching a second time is what made every JUser key appear twice in the
        //application log. The trait now refuses the duplicate, but asking for it
        //at all was the mistake.
        $table->setPersistentCache($container->get('JUser\Cache'));

        $mailer = $container->get(Mailer::class);
        $table->setMailer($mailer);

        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $table->setLogger($logger);
        }

        return $table;
    }
}
