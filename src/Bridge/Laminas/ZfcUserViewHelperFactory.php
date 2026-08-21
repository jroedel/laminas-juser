<?php

namespace JUser\Bridge\Laminas;

use Psr\Container\ContainerInterface;

/**
 * Builds any of the JUser view helpers that take only the AuthenticationService.
 */
class ZfcUserViewHelperFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new $requestedName($container->get('JUser\AuthService'));
    }
}
