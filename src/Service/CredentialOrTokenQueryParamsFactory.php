<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Authentication\Adapter\CredentialOrTokenQueryParams;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CredentialOrTokenQueryParamsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $adapter = new CredentialOrTokenQueryParams();
        $adapter->setServiceManager($container);
        return $adapter;
    }
}
