<?php

namespace JUser\Bridge\Laminas;

use Psr\Container\ContainerInterface;

class AuthServiceActingUserProviderFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new AuthServiceActingUserProvider($container);
    }
}
