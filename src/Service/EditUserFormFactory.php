<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Filter\HashPasswordForLmcUser;
use JUser\Form\EditUserForm;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class EditUserFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $adapter                = $container->get(Adapter::class);
        $hashPasswordForLmcUser = $container->get(HashPasswordForLmcUser::class);
        $config                 = $container->get('JUser\Config');
        if (isset($config['person_provider'])) {
            Assert::true($container->has($config['person_provider']));
            $provider = $container->get($config['person_provider']);
            Assert::isInstanceOf($provider, PersonValueOptionsProviderInterface::class);
            $personValueOptions = $provider->getPersonValueOptions();
        } else {
            $personValueOptions = null;
        }
        /** @var UserTable $userTable **/
        $userTable         = $container->get(UserTable::class);
        $rolesValueOptions = $userTable->getRolesValueOptions();
        return new EditUserForm($adapter, $hashPasswordForLmcUser, $rolesValueOptions, $personValueOptions);
    }
}
