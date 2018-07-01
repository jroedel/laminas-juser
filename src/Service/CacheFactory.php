<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Zend\Cache\Storage\StorageInterface;
use Zend\Cache\StorageFactory;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory for building the cache storage
 *
 * @author Christian Bergau <cbergau86@gmail.com>
 */
class CacheFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('config');
        if (!isset($config['juser']) || !isset($config['juser']['cache_options'])) {
            throw new \Exception('Missing JUser cache configuration');
        }

        return StorageFactory::factory($config['juser']['cache_options']);
    }
}
