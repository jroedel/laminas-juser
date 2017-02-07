<?php
namespace JUser\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the JUser configuration
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class ConfigServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config');

        return $config['juser'];
    }
}
