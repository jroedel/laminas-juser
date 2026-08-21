<?php

namespace JUser\Service;

use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Cache\LegacyCacheConfig;

/**
 * Factory for building the cache storage
 */
class CacheFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('config');
        if (! isset($config['juser']) || ! isset($config['juser']['cache_options'])) {
            throw new \Exception('Missing JUser cache configuration');
        }

        return $container->get(StorageAdapterFactoryInterface::class)
            ->createFromArrayConfiguration(LegacyCacheConfig::translate($config['juser']['cache_options']));
    }
}
