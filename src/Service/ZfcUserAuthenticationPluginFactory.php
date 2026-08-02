<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Controller\Plugin\ZfcUserAuthentication;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ZfcUserAuthenticationPluginFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ZfcUserAuthentication($container->get('JUser\AuthService'));
    }
}
