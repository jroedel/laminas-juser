<?php

namespace JUser\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the JUser configuration
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ConfigServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');

        return $config['juser'];
    }
}
