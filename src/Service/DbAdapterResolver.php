<?php

declare(strict_types=1);

namespace JUser\Service;

use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;

/**
 * The one place that answers "which db adapter did this application configure JUser
 * with?".
 *
 * The `['juser']['db_adapter']` config key predates this class and is read by four
 * factories; what is new is that the answer now reaches the three forms as a
 * constructor argument instead of through
 * `Laminas\Db\TableGateway\Feature\GlobalAdapterFeature`'s static registry, which
 * `JUser\Module::onBootstrap()` populated and nothing else did. That registry was the
 * reason `CreateRoleForm`, `DeleteUserForm` and JTranslate's `EditPhraseForm` threw
 * from `getInputFilterSpecification()` in any process without laminas-mvc — the
 * Symfony kernel, a console command, a test harness — and the reason `EditUserForm`
 * silently dropped its two uniqueness validators there instead.
 *
 * The explicit exception is kept from `onBootstrap()`, which is where it used to
 * live: a missing service would otherwise surface as a `ServiceNotFoundException`
 * naming `Laminas\Db\Adapter\Adapter` and no reason for a host application to suspect
 * its own JUser config.
 */
final class DbAdapterResolver
{
    public static function fromContainer(ContainerInterface $container): Adapter
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('Config');
        /** @var array<string, mixed> $juser */
        $juser = $config['juser'] ?? [];
        /** @var string $service */
        $service = $juser['db_adapter'] ?? Adapter::class;

        if (! $container->has($service)) {
            throw new \RuntimeException(
                'Please set the [\'juser\'][\'db_adapter\'] config key for use with the JUser module.'
            );
        }

        /** @var Adapter $adapter */
        $adapter = $container->get($service);

        return $adapter;
    }
}
