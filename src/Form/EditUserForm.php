<?php

namespace JUser\Form;

use Laminas\Db\Adapter\Adapter;
use SionModel\Form\Form;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Validator\Regex;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;

class EditUserForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;
    protected $hasPersonData = false;

    /**
     * Filter specification turning an absent checkbox into the unchecked value.
     * Declared once because all four checkboxes need exactly the same thing.
     */
    private const UNCHECKED_WHEN_ABSENT = [
        'name' => 'Callback',
        'options' => ['callback' => [self::class, 'uncheckedWhenAbsent']],
    ];

    /**
     * The adapter the two `NoRecordExists` validators in setValidatorsForCreate()
     * need.
     *
     * Required, and passed in rather than read from
     * `GlobalAdapterFeature::getStaticAdapter()`. This form failed differently from
     * its siblings and worse: its static reads sat inside a
     * `try { … } catch (\Exception $e) {}`, so an empty registry did not throw — it
     * silently dropped both uniqueness checks, and a create-user POST carrying an
     * existing username or display name validated clean. With the adapter injected
     * that state is unreachable rather than unlikely.
     */
    public function __construct(private readonly Adapter $adapter, $name = null)
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
            'name' => 'emailVerified',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Email verified',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value'           => 0,
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
                'checked_value' => '1',
                'unchecked_value' => '0',
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

    /**
     * An absent checkbox is an unchecked checkbox, not a missing value.
     *
     * @param mixed $value
     * @return mixed the unchecked value when nothing was posted, else $value
     */
    public static function uncheckedWhenAbsent($value)
    {
        return null === $value ? '0' : $value;
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

    public function prepareForEdit()
    {
        $this->setValidationGroup(array_keys($this->getElements()));
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
            'security' => CsrfSpec::forElement($this->get('security')),
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
            //`disable_inarray_validator` is set on the element, so this entry is
            //the only domain check the field has ever had a chance of carrying -
            //see SionModel\Form\ChoiceDomain. The haystack is whatever
            //setPersonValueOptions() supplied; before an admin screen calls that,
            //the element has no options and ChoiceDomain answers no validator
            //rather than refusing every submission.
            'personId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('personId')),
            ],
            /**
             * All four checkboxes map to NOT NULL columns, and an unticked
             * checkbox is simply absent from the POST — so without something
             * here the table is handed a null and MySQL rejects the insert.
             *
             * A one-line filter rather than `fallback_value`, which looks like
             * the right tool and silently is not:
             * Laminas\Form\Form::attachInputFilterDefaults() rebuilds the input
             * for every element that provides its own specification (every
             * Checkbox does, for its InArray validator) and merges this
             * specification into it — and Laminas\InputFilter\Input::merge()
             * copies `required`, `allowEmpty`, the filters and the validators,
             * but *not* the fallback. Declaring one therefore leaves the field
             * required with nothing to fall back to, and the form rejects a
             * submission with no message against any field the view renders.
             * Filters survive the merge, which is why this works and is how
             * emailVerified and active were already being saved.
             *
             * Only null is rewritten. A value that really was posted is left
             * for the element's own InArray validator to judge, so this closes
             * the absent case without turning the checkbox into a field that
             * accepts anything.
             */
            //Unlike personId above, this select keeps its own InArray — nothing disables
            //it — so the domain is already the role list setRoleValueOptions() supplies
            //and there is nothing for ChoiceDomain to put back. The entry exists to
            //declare `required`, which was reaching the input from
            //Select::getInputSpecification() and from nowhere a reader of this method
            //could see.
            'rolesList' => [
                'required' => true,
                'validators' => ChoiceDomain::validators($this->get('rolesList')),
            ],
            'emailVerified' => [
                'required' => false,
                'filters' => [self::UNCHECKED_WHEN_ABSENT],
                'validators' => CheckboxDomain::validators($this->get('emailVerified')),
            ],
            'isMultiPersonUser' => [
                'required' => false,
                'filters' => [self::UNCHECKED_WHEN_ABSENT],
                'validators' => CheckboxDomain::validators($this->get('isMultiPersonUser')),
            ],
            'active' => [
                'required' => false,
                'filters' => [self::UNCHECKED_WHEN_ABSENT],
                'validators' => CheckboxDomain::validators($this->get('active')),
            ],
        ];
        return $this->filterSpec;
    }

    public function setValidatorsForCreate()
    {
        $spec = $this->getInputFilterSpecification();
        if ($spec && isset($spec['userId']) && $spec['userId']) {
            $spec['userId']['required'] = false;
        }
        //No try block: the adapter is a constructor dependency now, so there is
        //nothing here that can fail. The one this replaced swallowed every
        //exception, which meant the only way to lose these two validators was
        //also the only way to lose them silently.
        if ($spec && isset($spec['displayName']) && $spec['displayName']) {
            $spec['displayName']['validators'][] = [
                'name'    => 'SionModel\Validator\Db\NoRecordExists',
                'options' => [
                    'table' => 'user',
                    'field' => 'display_name',
                    'adapter' => $this->adapter,
                    'messages' => [
                        \SionModel\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                            => 'Display name already exists in database'
                    ],
                ],
            ];
        }
        if ($spec && isset($spec['username']) && $spec['username']) {
            $spec['username']['validators'][] = [
                'name'    => 'SionModel\Validator\Db\NoRecordExists',
                'options' => [
                    'table' => 'user',
                    'field' => 'username',
                    'adapter' => $this->adapter,
                    'messages' => [
                        \SionModel\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND
                            => 'Username already exists in database'
                    ],
                ],
            ];
        }
        $this->setInputFilterSpecification($spec);
    }
}
