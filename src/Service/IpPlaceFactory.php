<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\View\Helper\IpPlace;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IpPlaceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $geo = $container->get('GeoIp2');
        return new IpPlace($geo);
    }
}
