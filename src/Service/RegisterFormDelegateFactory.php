<?php

declare(strict_types=1);

namespace JUser\Service;

use Laminas\Filter\StringTrim;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use LmcUser\Form\Register;
use Psr\Container\ContainerInterface;
use SionModel\Validator\Instagram;

use function call_user_func;

class RegisterFormDelegateFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, ?array $options = null)
    {
        /** @var Register $form */
        $form = call_user_func($callback);

        // Set filters and validators
        $inputFilter = $form->getInputFilter();

        $inputFilter->add([
            'name'       => 'username',
            'required'   => true,
            'filters'    => [
                new StringTrim(),
            ],
            'validators' => [
                new Instagram(),
            ],
        ]);
        return $form;
    }
}
