<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use ZfcUser\Form;

class LoginFilterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceManager, $requestedName, array $options = null)
    {
        $options = $serviceManager->get('zfcuser_module_options');
        $inputFilter = new Form\LoginFilter($options);

        return $inputFilter;
    }
}
