<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JUser\Provider\Identity\ZfcUserZendDbPlusSelfAsRole;

/**
 * Factory responsible of instantiating {@see \BjyAuthorize\Provider\Identity\ZfcUserZendDb}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ZfcUserZendDbPlusSelfAsRoleFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /* @var $tableGateway \Zend\Db\TableGateway\TableGateway */
        $tableGateway = new TableGateway('user_role_linker', $container->get('zfcuser_zend_db_adapter'));
        /* @var $userService \ZfcUser\Service\User */
        $userService = $container->get('zfcuser_user_service');
        $config = $container->get('BjyAuthorize\Config');

        $provider = new ZfcUserZendDbPlusSelfAsRole($tableGateway, $userService);

        $provider->setDefaultRole($config['default_role']);

        return $provider;
    }

    /**
     * {@inheritDoc}
     *
     * @return \BjyAuthorize\Provider\Identity\ZfcUserZendDb
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, ZfcUserZendDbPlusSelfAsRole::class);
    }
}
