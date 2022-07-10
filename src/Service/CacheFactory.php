<?php

namespace JUser\Service;

use Laminas\Cache\StorageFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CacheFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('config');
        if (! isset($config['juser']) || ! isset($config['juser']['cache_options'])) {
            throw new \Exception('Missing JUser cache configuration');
        }

        return StorageFactory::factory($config['juser']['cache_options']);
    }
}
