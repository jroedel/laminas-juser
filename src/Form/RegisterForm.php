<?php

namespace  JUser\Form;

use ZfcUser\Form\Register;
use ZfcUser\Options\RegistrationOptionsInterface;

class RegisterForm extends Register
{

    /**
     * NOT YET IMPLEMENTED
     * @TODO IMPLEMENT
     * @param string|null $name
     * @param RegistrationOptionsInterface $options
     */
    public function __construct($name, RegistrationOptionsInterface $options)
    {
        parent::__construct($name, $options);
    }
}
