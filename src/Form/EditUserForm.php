<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class EditUserForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;
    protected $hasPersonData = false;

    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('user_edit');

        $this->setAttribute('method', 'post');
        $this->add([
            'name' => 'userId',
            'attributes' => [
                'type' => 'hidden'
            ],
        ]);
        $this->add([
            'name' => 'username',
            'attributes' => [
                'type' => 'text',
                'size' => '30'
            ],
            'options' => [
                'label' => 'Username'
            ],
        ]);
        $this->add([
            'name' => 'email',
            'attributes' => [
                'type' => 'text',
                'size' => '50'
            ],
            'options' => [
                'label' => 'Email'
            ],
        ]);
        $this->add([
            'name' => 'displayName',
            'attributes' => [
                'type' => 'text',
                'size' => '50'
            ],
            'options' => [
                'label' => 'Display Name'
            ],
        ]);

        $this->add([
            'name' => 'password',
            'type' => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options' => [
                'label' => 'Password',
            ],
        ]);
        $this->add([
            'name' => 'passwordVerify',
            'type' => 'Password',
            'attributes' => [
                'required' => true,
            ],
            'options' => [
                'label' => 'Verify Password',
            ],
        ]);
        $this->add([
            'name' => 'emailVerified',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Email verified',
                'checked_value' => 1,
                'unchecked_value' => 0,
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value'           => 0,
            ],
        ]);
        $this->add([
            'name' => 'mustChangePassword',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Must change password at next logon?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isMultiPersonUser',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Multi-person user?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'active',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Active',
                'checked_value' => 1,
                'unchecked_value' => 0,
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value'           => 0,
            ],
        ]);
        $this->add([
            'name' => 'rolesList',
            'type' => 'Select',
            'attributes' => [
                'multiple' => 'multiple',
            ],
            'options' => [
                'label' => 'Roles',
            ],
        ]);
        $this->add([
            'name' => 'personId',
            'type' => 'Select',
            'options' => [
                'label' => 'Person reference',
                'empty_option' => '',
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
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

    public function getHasPersonData()
    {
        return $this->hasPersonData;
    }

    /**
     * Set value options for the personId field
     * @param bool $hasPersonData
     * @return \JUser\Form\EditUserForm
     */
    public function setPersonValueOptions(array $personValueOptions)
    {
        $this->get('personId')->setValueOptions($personValueOptions);
        $this->hasPersonData = true;
        return $this;
    }
    
    /**
     * Don't update password while on edit form
     */
    public function prepareForEdit()
    {
        $fields = $this->getElements();
        unset($fields['password']);
        unset($fields['passwordVerify']);
        $this->setValidationGroup(array_keys($fields));
    }

    public function setInputFilterSpecification($spec)
    {
        $this->filterSpec = $spec;
    }

    public function getInputFilterSpecification()
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        $this->filterSpec = [
            'userId' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'Int'],
                    ['name' => 'ToNull'],
                ],
            ],
            'username' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StripTags'],
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => [
                            'pattern' => '/\A[0-9A-Za-z-_.]+\z/',
                        ],
                        'messages' => [
                            Regex::INVALID => 'Please use only numbers, letters, dash, underscore or period.'
                        ],
                    ],
                ],
            ],
            'email' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => 'EmailAddress',
                    ],
                ],
            ],
            'displayName' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 4,
                            'max' => 40
                        ],
                    ],
                ],
            ],
            'personId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
            'emailVerified' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Boolean'],
                ],
            ],
            'mustChangePassword' => [
                'required' => false,
            ],
            'isMultiPersonUser' => [
                'required' => false,
            ],
            'active' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Boolean'],
                ],
            ],
            'password' => [
                'required'   => false,
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
            'passwordVerify' => [
                'required'   => false,
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
                            'token' => 'password'
                        ],
                    ],
                ],
                'filters'   => [
                    ['name' => 'StringTrim'],
                ],
            ]
        ];
        return $this->filterSpec;
    }

    public function setValidatorsForCreate()
    {
        $spec = $this->getInputFilterSpecification();
        if ($spec && isset($spec['userId']) && $spec['userId']) {
            $spec['userId']['required'] = false;
        }
        try { //use try block in case there is no StaticAdapter
            if ($spec && isset($spec['displayName']) && $spec['displayName']) {
                $spec['displayName']['validators'][] = [
                    'name'    => 'Zend\Validator\Db\NoRecordExists',
                    'options' => [
                        'table' => 'user',
                        'field' => 'display_name',
                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                        'messages' => [
                            \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                                => 'Display name already exists in database'
                        ],
                    ],
                ];
            }
            if ($spec && isset($spec['username']) && $spec['username']) {
                $spec['username']['validators'][] = [
                    'name'    => 'Zend\Validator\Db\NoRecordExists',
                    'options' => [
                        'table' => 'user',
                        'field' => 'username',
                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                        'messages' => [
                            \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                                => 'Username already exists in database'
                        ],
                    ],
                ];
            }
        } catch (\Exception $e) {
        }
        $this->setInputFilterSpecification($spec);
    }
}
