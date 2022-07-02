<?php
namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use SionModel\Validator\Instagram;

class RegisterFormDelegateFactory implements DelegatorFactoryInterface
{
    /**
     * This delegate adds more elements to the form
     * @inheritDoc
     * @see \Laminas\ServiceManager\Factory\DelegatorFactoryInterface::__invoke()
     *
     */
    public function __invoke(ContainerInterface $container, $name, callable $callback, ?array $options = null)
    {
        /** @var \LmcUser\Form\Register $form */
        $form = call_user_func($callback);
        
        // Set filters and validators
        $inputFilter = $form->getInputFilter();
        
        $inputFilter->add([
            'name' => 'username',
            'required' => true,
            'filters' => [
                new \Laminas\Filter\StringTrim(),
            ],
            'validators' => [
                new Instagram(),
            ],
        ]);
        return $form;
    }
}