<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use JUser\Model\ApiTokenTable;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiTokenServiceFactory implements FactoryInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        $juserConfig = isset($config['juser']) ? $config['juser'] : [];

        //The signing key lives under ApiRequest, not juser: it is the *application's*
        //API key, shared with RestApi's own JWT handling, and a second copy under a
        //second key is how two halves of a credential system drift apart. Read
        //leniently here — an empty key is not a boot failure, it is a failure at the
        //moment somebody tries to issue a token, where ApiTokenService can say so.
        $jwtConfig = isset($config['ApiRequest']['jwtAuth']) && is_array($config['ApiRequest']['jwtAuth'])
            ? $config['ApiRequest']['jwtAuth']
            : [];

        $service = new ApiTokenService(
            $container->get(ApiTokenTable::class),
            $container->get(UserTable::class),
            isset($jwtConfig['cypherKey']) ? $jwtConfig['cypherKey'] : '',
            isset($jwtConfig['tokenAlgorithm']) ? $jwtConfig['tokenAlgorithm'] : 'HS256',
            $juserConfig
        );

        if ($container->has('JUser\Logger')) {
            $service->setLogger($container->get('JUser\Logger'));
        }

        return $service;
    }
}
