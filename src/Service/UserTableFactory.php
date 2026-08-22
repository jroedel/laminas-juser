<?php

namespace JUser\Service;

use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

class UserTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //SionTable takes its four required collaborators explicitly since 2026-08-22. It
        //used to take the container and pull six services out of it, one of which was the
        //MVC `Application`, for its event manager — laminas-mvc, reached from a data class.
        $table = new UserTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class)
        );

        //Cache, logger and the user-directory resolver, which are the optional three.
        //Wiring the *user table* with a user directory sounds circular and is not: the
        //resolver is a closure, and SionTable discards an answer identical to itself.
        SionTableWiring::apply($container, $table);

        //JUser keeps its own APCu namespace and TTL (juser.cache_options), so it replaces
        //the application-wide `SionModel\PersistentCache` that the wiring just attached.
        //Replacing the storage discards the dependency map that came with it — see
        //SionCacheTrait::setPersistentCache().
        //
        //No wireOnFinishTrigger() here: apply() already attached it, and attaching a second
        //time is what made every JUser cache key appear twice in the application log. The
        //trait now refuses the duplicate, but asking for it at all was the mistake.
        $table->setPersistentCache($container->get('JUser\Cache'));

        $mailer = $container->get(Mailer::class);
        $table->setMailer($mailer);

        //JUser's own logger wins over the application-wide one apply() may have set.
        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $table->setLogger($logger);
        }

        return $table;
    }
}
