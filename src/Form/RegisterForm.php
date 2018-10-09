<?php
namespace  JUser\Form;

use ZfcUser\Form\Register;

class RegisterForm extends Register
{
    
    /**
     * @param string|null $name
     * @param RegistrationOptionsInterface $options
     */
    public function __construct($name, RegistrationOptionsInterface $options)
    {
        parent::__construct($name, $options);
        $this->add()
    }
}