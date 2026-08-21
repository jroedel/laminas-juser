<?php

namespace JUser\Bridge\Laminas;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Builds any of the JUser view helpers that take only the AuthenticationService.
 */
class ZfcUserViewHelperFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new $requestedName($container->get('JUser\AuthService'));
    }
}
