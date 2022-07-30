<?php

declare(strict_types=1);

namespace JUser\Form;

use Exception;
use JUser\Filter\HashPasswordForLmcUser;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Filter\Boolean;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Password;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Db\AbstractDb;
use Laminas\Validator\Db\NoRecordExists;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Identical;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Webmozart\Assert\Assert;

use function array_keys;

class EditUserForm extends Form implements InputFilterProviderInterface
{
    public const MIN_PASSWORD_LENGTH = 6;

    protected array $filterSpec = [];

    public function __construct(
        private AdapterInterface $adapter,
        private HashPasswordForLmcUser $hashPasswordForLmcUser,
        array $rolesValueOptions,
        ?array $personValueOptions
    ) {
        // we want to ignore the name passed
        parent::__construct('user_edit');

        $this->setAttribute('method', 'post');
        $this->add([
            'name'       => 'username',
            'attributes' => [
                'type' => Text::class,
                'size' => '30',
            ],
            'options'    => [
                'label' => 'Username',
            ],
        ]);
        $this->add([
            'name'       => 'email',
            'attributes' => [
                'type' => Text::class,
                'size' => '50',
            ],
            'options'    => [
                'label' => 'Email',
            ],
        ]);
        $this->add([
            'name'       => 'displayName',
            'attributes' => [
                'type' => Text::class,
                'size' => '50',
            ],
            'options'    => [
                'label' => 'Display Name',
            ],
        ]);

        $this->add([
            'name'       => 'password',
            'type'       => Password::class,
            'attributes' => [
                'required' => true,
            ],
            'options'    => [
                'label' => 'Password',
            ],
        ]);
        $this->add([
            'name'       => 'passwordVerify',
            'type'       => Password::class,
            'attributes' => [
                'required' => true,
            ],
            'options'    => [
                'label' => 'Verify Password',
            ],
        ]);
        $this->add([
            'name'       => 'emailVerified',
            'type'       => Checkbox::class,
            'options'    => [
                'label'              => 'Email verified',
                'checked_value'      => '1',
                'unchecked_value'    => '0',
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value' => 0,
            ],
        ]);
        $this->add([
            'name'       => 'mustChangePassword',
            'type'       => Checkbox::class,
            'options'    => [
                'label'              => 'Must change password at next logon?',
                'checked_value'      => '1',
                'unchecked_value'    => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);
        $this->add([
            'name'       => 'isMultiPersonUser',
            'type'       => Checkbox::class,
            'options'    => [
                'label'              => 'Multi-person user?',
                'checked_value'      => '1',
                'unchecked_value'    => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);
        $this->add([
            'name'       => 'active',
            'type'       => Checkbox::class,
            'options'    => [
                'label'              => 'Active',
                'checked_value'      => '1',
                'unchecked_value'    => '0',
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value' => 0,
            ],
        ]);

        Assert::notEmpty($rolesValueOptions);
        $this->add([
            'name'       => 'rolesList',
            'type'       => Select::class,
            'options'    => [
                'value_options' => $rolesValueOptions,
                'label'         => 'Roles',
            ],
            'attributes' => [
                'multiple' => 'multiple',
            ],
        ]);

        if (isset($personValueOptions)) {
            $this->add([
                'name'       => 'personId',
                'type'       => Select::class,
                'options'    => [
                    'label'         => 'Person reference',
                    'empty_option'  => '',
                    'value_options' => $personValueOptions,
                ],
                'attributes' => [],
            ]);
        } else {
            $this->add([
                'name'       => 'personId',
                'type'       => Select::class,
                'options'    => [
                    'label'                     => 'Person reference',
                    'empty_option'              => '',
                    'disable_inarray_validator' => true,
                ],
                'attributes' => [],
            ]);
        }
        $this->add([
            'name' => 'security',
            'type' => Csrf::class,
        ]);
        $this->add([
            'name'       => 'submit',
            'attributes' => [
                'class' => 'btn-primary',
                'type'  => Submit::class,
                'value' => 'Submit',
                'id'    => 'submit',
            ],
        ]);
    }

    /**
     * Don't update password while on edit form
     */
    public function prepareForEdit(): void
    {
        $fields = $this->getElements();
        unset($fields['password']);
        unset($fields['passwordVerify']);
        $this->setValidationGroup(array_keys($fields));
    }

    public function setInputFilterSpecification(array $spec): void
    {
        $this->filterSpec = $spec;
    }

    /**
     * @return array
     */
    public function getInputFilterSpecification()
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        $this->filterSpec = [
            'username'           => [
                'required'   => true,
                'filters'    => [
                    ['name' => StripTags::class],
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'     => Regex::class,
                        'options'  => [
                            'pattern' => '/\A[0-9A-Za-z-_.]+\z/',
                        ],
                        'messages' => [
                            Regex::INVALID => 'Please use only numbers, letters, dash, underscore or period.',
                        ],
                    ],
                ],
            ],
            'email'              => [
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => EmailAddress::class,
                    ],
                ],
            ],
            'displayName'        => [
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 4,
                            'max' => 40,
                        ],
                    ],
                ],
            ],
            'personId'           => [
                'required' => false,
                'filters'  => [
                    ['name' => ToInt::class],
                    ['name' => ToNull::class],
                ],
            ],
            'emailVerified'      => [
                'required' => false,
                'filters'  => [
                    ['name' => Boolean::class],
                ],
            ],
            'mustChangePassword' => [
                'required' => false,
            ],
            'isMultiPersonUser'  => [
                'required' => false,
            ],
            'active'             => [
                'required' => false,
                'filters'  => [
                    ['name' => Boolean::class],
                ],
            ],
            'password'           => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min' => self::MIN_PASSWORD_LENGTH,
                        ],
                    ],
                ],
                'filters'    => [
                    $this->hashPasswordForLmcUser,
                ],
            ],
            'passwordVerify'     => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => Identical::class,
                        'options' => [
                            'token' => 'password',
                        ],
                    ],
                ],
            ],
        ];
        return $this->filterSpec;
    }

    public function setValidatorsForCreate(): void
    {
        $spec = $this->getInputFilterSpecification();
        try { //use try block in case there is no StaticAdapter
            if ($spec && isset($spec['displayName']) && $spec['displayName']) {
                $spec['displayName']['validators'][] = [
                    'name'    => NoRecordExists::class,
                    'options' => [
                        'table'    => 'user',
                        'field'    => 'display_name',
                        'adapter'  => $this->adapter,
                        'messages' => [
                            AbstractDb::ERROR_RECORD_FOUND
                                => 'Display name already exists in database',
                        ],
                    ],
                ];
            }
            if ($spec && isset($spec['username']) && $spec['username']) {
                $spec['username']['validators'][] = [
                    'name'    => NoRecordExists::class,
                    'options' => [
                        'table'    => 'user',
                        'field'    => 'username',
                        'adapter'  => $this->adapter,
                        'messages' => [
                            AbstractDb::ERROR_RECORD_FOUND
                                => 'Username already exists in database',
                        ],
                    ],
                ];
            }
        } catch (Exception) {
        }
        $this->setInputFilterSpecification($spec);
    }
}
