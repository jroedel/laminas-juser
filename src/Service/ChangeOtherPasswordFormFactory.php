<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Filter\HashPasswordForLmcUser;
use JUser\Form\ChangeOtherPasswordForm;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ChangeOtherPasswordFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $form = new ChangeOtherPasswordForm($container->get(HashPasswordForLmcUser::class));
        return $form;
    }
}
