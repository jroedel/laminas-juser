<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Filter\HashPasswordForLmcUser;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class HashPasswordForLmcUserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $lmcModuleOptions = $container->get('lmcuser_module_options');
        return new HashPasswordForLmcUser($lmcModuleOptions);
    }
}
