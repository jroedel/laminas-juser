<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JUser\Authentication\Adapter\JsonPost;

class JsonPostFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = new JsonPost();
        $adapter->setServiceManager($container);
        return $adapter;
    }

    /**
     * {@inheritDoc}
     *
     * @return \BjyAuthorize\View\UnauthorizedStrategy
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, JsonPost::class);
    }
}
