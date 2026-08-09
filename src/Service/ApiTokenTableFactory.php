<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Model\ApiTokenTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiTokenTableFactory implements FactoryInterface
{
    /**
     * @inheritdoc
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
