<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;

class CredentialOrTokenQueryParamsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = new CredentialOrTokenQueryParams();
        $adapter->setServiceManager($container);
        return $adapter;
    }
}
