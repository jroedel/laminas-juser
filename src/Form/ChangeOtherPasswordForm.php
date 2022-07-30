<?php

declare(strict_types=1);

namespace JUser\Form;

use JUser\Filter\HashPasswordForLmcUser;
use Laminas\Filter\StringTrim;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Identical;
use Laminas\Validator\StringLength;

class ChangeOtherPasswordForm extends Form implements InputFilterProviderInterface
{
    public function __construct(private HashPasswordForLmcUser $hashPasswordForLmcUser)
    {
        // we want to ignore the name passed
        parent::__construct('change_other_password');
        $this->add([
            'name'       => 'newCredential',
            'type'       => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options'    => [
                'label' => 'Password',
            ],
        ]);
        $this->add([
            'name'       => 'newCredentialVerify',
            'type'       => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options'    => [
                'label' => 'Verify Password',
            ],
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => 'Change Password',
                'id'    => 'submit',
                'class' => 'btn-primary',
            ],
        ]);
        $this->add([
            'name'       => 'cancel',
            'type'       => 'Button',
            'attributes' => [
                'value'        => 'Cancel',
                'id'           => 'cancel',
                'data-dismiss' => 'modal',
            ],
        ]);
    }

    /**
     * @return array[]
     */
    public function getInputFilterSpecification()
    {
        return [
            'newCredential'       => [
                'name'       => 'newCredential',
                'required'   => true,
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min' => EditUserForm::MIN_PASSWORD_LENGTH,
                        ],
                    ],
                ],
                'filters'    => [
                    $this->hashPasswordForLmcUser,
                ],
            ],
            'newCredentialVerify' => [
                'name'       => 'newCredentialVerify',
                'required'   => true,
                'validators' => [
                    [
                        'name'    => Identical::class,
                        'options' => [
                            'token' => 'newCredential',
                        ],
                    ],
                ],
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
            ],
        ];
    }
}
