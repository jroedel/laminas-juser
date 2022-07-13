<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Authentication\Adapter\Jwt;
use Laminas\Filter\PregReplace;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Validator\Regex;
use Psr\Container\ContainerInterface;

class JwtFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $options    = $container->get('lmcuser_module_options');
        $userMapper = $container->get('lmcuser_user_mapper');
        $request    = $container->get('request');
        $logger     = $container->get('JUser\Logger');
        $config     = $container->get('config');
        $validator  = new Regex('/^Bearer (.*)$/');
        $filter     = new PregReplace([
            'pattern'     => '/^Bearer /',
            'replacement' => '',
        ]);
        return new Jwt(
            mapper: $userMapper,
            options: $options,
            request: $request,
            logger: $logger,
            authorizationHeaderValidator: $validator,
            authorizationHeaderFilter: $filter,
            config: $config
        );
    }
}
