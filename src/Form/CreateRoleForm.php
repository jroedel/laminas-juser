<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class CreateRoleForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;

    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('role_create');

        $this->setAttribute('method', 'post');
        $this->add([
            'name' => 'name',
            'attributes' => [
                'type' => 'text',
                'size' => '30'
            ],
            'options' => [
                'label' => 'Role name'
            ]
        ]);

        $this->add([
            'name' => 'parentId',
            'type' => 'Select',
            'options' => [
                'label' => 'Parent',
                'empty_option' => '',
                'unselected_value' => '',
            ],
        ]);

        $this->add([
            'name' => 'isDefault',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Automatically give to new users?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
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
                'type' => 'submit',
                'value' => 'Submit',
                'id' => 'submit'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'name' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StripTags'],
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => [
                            'pattern' => '/\A[0-9A-Za-z_]+\z/',
                        ],
                        'messages' => [
                            Regex::INVALID => 'Please use only numbers, letters, or underscore.'
                        ],
                    ],
                    [
                        'name'    => 'Zend\Validator\Db\NoRecordExists',
                        'options' => [
                            'table' => 'user_role',
                            'field' => 'role_id',
                            'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                            'messages' => [
                                \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                                    => 'Role id already exists in database'
                            ],
                        ],
                    ],
                ],
            ],
            'isDefault' => [
                'required' => false,
            ],
            'parentId' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'ToNull'],
                ],
            ],
        ];
    }
}
