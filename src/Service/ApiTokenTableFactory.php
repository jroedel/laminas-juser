<?php

namespace JUser\Service;

use Psr\Container\ContainerInterface;
use JUser\Model\ApiTokenTable;
use Laminas\Db\Adapter\Adapter;

class ApiTokenTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //`Adapter::class`, not `AdapterInterface::class`: the application registers
        //the concrete class and nothing aliases the interface, so asking for the
        //interface is a ServiceNotCreatedException at the first request that needs
        //a token rather than an error anyone sees at boot.
        return new ApiTokenTable($container->get(Adapter::class));
    }
}
