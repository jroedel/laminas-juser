<?php

declare(strict_types=1);

namespace JUser\Service;

use InvalidArgumentException;
use JUser\Form\EditUserForm;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Model\UserTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EditUserFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var UserTable $userTable **/
        $userTable      = $container->get(UserTable::class);
        $form           = new EditUserForm();
        $config         = $container->get('JUser\Config');
        $personProvider = $config['person_provider'];
        if ($container->has($personProvider)) {
            /** @var PersonValueOptionsProviderInterface $provider **/
            $provider = $container->get($personProvider);
            if (! $provider instanceof PersonValueOptionsProviderInterface) {
                throw new InvalidArgumentException(
                    '`person_provider` specified in the JUser config does not implement'
                    . ' the PersonValueOptionsProviderInterface.'
                );
            }
            $persons = $provider->getPersonValueOptions();
            $form->setPersonValueOptions($persons);
        } else {
            throw new InvalidArgumentException(
                '`person_provider` specified in the JUser config does not exist.'
            );
        }
        $roles = $userTable->getRolesValueOptions();
        $form->get('rolesList')->setValueOptions($roles);

        return $form;
    }
}
