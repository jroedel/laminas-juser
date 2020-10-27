<?php

namespace JUser\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use ZfcUser\Options\ModuleOptions;

class ChangeOtherPasswordForm extends Form implements InputFilterProviderInterface
{
    /**
     *
     * @var ModuleOptions $zfcOptions
     */
    protected $zfcOptions;

    public function __construct(ModuleOptions $zfcOptions)
    {
        // we want to ignore the name passed
        parent::__construct('change_other_password');
        $this->zfcOptions = $zfcOptions;

        $this->add([
            'name' => 'userId',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name' => 'newCredential',
            'type' => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options' => [
                'label' => 'Password',
            ],
        ]);
        $this->add([
            'name' => 'newCredentialVerify',
            'type' => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options' => [
                'label' => 'Verify Password',
            ],
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Change Password',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
        $this->add([
            'name' => 'cancel',
            'type' => 'Button',
            'attributes' => [
                'value' => 'Cancel',
                'id' => 'cancel',
                'data-dismiss' => 'modal'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'userId' => [
                'required' => true,
                'validators' => [
                    [
                        'name'    => 'Zend\Validator\Db\RecordExists',
                        'options' => [
                            'table' => $this->zfcOptions->getTableName(),
                            'field' => 'user_id',
                            'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                            'messages' => [
                                \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'User not found in database'
                            ],
                        ],
                    ],
                ],
            ],
            'newCredential' => [
                'name'       => 'newCredential',
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 4,
                        ],
                    ],
                ],
                'filters'   => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'newCredentialVerify' => [
                'name'       => 'newCredentialVerify',
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 4,
                        ],
                    ],
                    [
                        'name' => 'identical',
                        'options' => [
                            'token' => 'newCredential'
                        ],
                    ],
                ],
                'filters'   => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ];
    }
}
