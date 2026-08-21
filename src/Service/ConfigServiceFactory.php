<?php

namespace JUser\Service;

use Psr\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the JUser configuration
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ConfigServiceFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');

        return $config['juser'];
    }
}
