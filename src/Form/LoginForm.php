<?php

namespace JUser\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

/**
 * The one and only sign-in form: an email address. There is no password.
 */
class LoginForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        parent::__construct($name ?: 'login');

        $this->setAttribute('method', 'post');

        $this->add([
            'name' => 'email',
            'type' => 'Email',
            'attributes' => [
                'id'           => 'email',
                'size'         => '40',
                'required'     => true,
                'autofocus'    => true,
                'autocomplete' => 'email',
                'placeholder'  => 'you@example.com',
            ],
            'options' => [
                'label' => 'Email address',
            ],
        ]);
        $this->add([
            'name' => 'redirect',
            'attributes' => [
                'type' => 'hidden',
            ],
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name' => 'submit',
            'attributes' => [
                'class' => 'btn-primary',
                'type'  => 'submit',
                'value' => 'Send me a sign-in link',
                'id'    => 'submit',
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'email' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => 'StripTags'],
                ],
                'validators' => [
                    ['name' => 'EmailAddress'],
                    [
                        'name' => 'StringLength',
                        'options' => ['max' => 255],
                    ],
                ],
            ],
            'redirect' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ];
    }
}
