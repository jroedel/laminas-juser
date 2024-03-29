<?php

declare(strict_types=1);

namespace JUser\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Form;
use Psr\Container\ContainerInterface;

class LoginFilterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $options = $container->get('lmcuser_module_options');
        return new Form\LoginFilter($options);
    }
}
