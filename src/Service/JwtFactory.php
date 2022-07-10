<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Authentication\Adapter\Jwt;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class JwtFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $adapter = new Jwt();
        $adapter->setServiceManager($container);
        return $adapter;
    }
}
