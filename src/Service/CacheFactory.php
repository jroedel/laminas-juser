<?php

declare(strict_types=1);

namespace JUser\Service;

use Exception;
use Psr\Container\ContainerInterface;
use SionModel\Cache\Storage;
use SionModel\Cache\StorageFactory;

use function is_array;

/**
 * `JUser\Cache`: this module's own namespace in the shared cache backend, with its own TTL.
 *
 * Separate from the application-wide cache on purpose — the user table's entries expire on
 * their own schedule, and the namespace is what keeps one cache's flush from emptying the
 * other's keys.
 *
 * The configuration is unchanged; `SionModel\Cache\StorageFactory` reads the same
 * laminas-cache shape `juser.cache_options` has always been written in. What it returns is
 * `SionModel\Cache\ApcuStorage`, which needs no laminas-cache.
 */
class CacheFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     * @throws Exception when the host configures no cache for this module.
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Storage
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('config');
        if (! is_array($config['juser']['cache_options'] ?? null)) {
            throw new Exception('Missing JUser cache configuration');
        }

        return StorageFactory::fromConfig($config['juser']['cache_options']);
    }
}
