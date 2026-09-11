<?php

namespace JUser\Form;

use Laminas\Db\Adapter\Adapter;
use SionModel\Form\Form;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Validator\Regex;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;

class CreateRoleForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;

    /**
     * The adapter the `NoRecordExists` validator below needs.
     *
     * Required, and passed in rather than read from
     * `GlobalAdapterFeature::getStaticAdapter()`. See DeleteUserForm for what the
     * static registry cost.
     */
    public function __construct(private readonly Adapter $adapter, $name = null)
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
            'security' => CsrfSpec::forElement($this->get('security')),
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
                        'name'    => 'SionModel\Validator\Db\NoRecordExists',
                        'options' => [
                            'table' => 'user_role',
                            'field' => 'role_id',
                            'adapter' => $this->adapter,
                            'messages' => [
                                \SionModel\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                                    => 'Role id already exists in database'
                            ],
                        ],
                    ],
                ],
            ],
            'isDefault' => [
                'required' => false,
                'validators' => CheckboxDomain::validators($this->get('isDefault')),
            ],
            //The InArray this select's own options imply, which naming it here
            //would otherwise discard - see SionModel\Form\ChoiceDomain. The
            //haystack is every role in user_role, which is what the factory sets
            //on the element before this specification is ever built.
            'parentId' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('parentId')),
            ],
        ];
    }
}
