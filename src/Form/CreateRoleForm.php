<?php

declare(strict_types=1);

namespace JUser\Form;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToNull;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Db\AbstractDb;
use Laminas\Validator\Db\NoRecordExists;
use Laminas\Validator\Regex;
use SionModel\Entity\Entity;
use Webmozart\Assert\Assert;

class CreateRoleForm extends Form implements InputFilterProviderInterface
{
    public function __construct(
        private AdapterInterface $adapter,
        private Entity $userRoleEntitySpec,
        array $rolesValueOptions
    ) {
        Assert::keyExists($this->userRoleEntitySpec->updateColumns, 'name');
        // we want to ignore the name passed
        parent::__construct('role_create');

        $this->setAttribute('method', 'post');
        $this->add([
            'name'       => 'name',
            'attributes' => [
                'type' => 'text',
                'size' => '30',
            ],
            'options'    => [
                'label' => 'Role name',
            ],
        ]);

        $this->add([
            'name'    => 'parentId',
            'type'    => 'Select',
            'options' => [
                'label'            => 'Parent',
                'empty_option'     => '',
                'unselected_value' => '',
                'value_options'    => $rolesValueOptions,
            ],
        ]);

        $this->add([
            'name'       => 'isDefault',
            'type'       => 'Checkbox',
            'options'    => [
                'label'              => 'Automatically give to new users?',
                'checked_value'      => '1',
                'unchecked_value'    => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name'       => 'submit',
            'attributes' => [
                'class' => 'btn-primary',
                'type'  => 'submit',
                'value' => 'Submit',
                'id'    => 'submit',
            ],
        ]);
    }

    /**
     * @return array
     */
    public function getInputFilterSpecification()
    {
        return [
            'name'      => [
                'required'   => true,
                'filters'    => [
                    ['name' => StripTags::class],
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'     => Regex::class,
                        'options'  => [
                            'pattern' => '/\A[0-9A-Za-z_]+\z/',
                        ],
                        'messages' => [
                            Regex::INVALID => 'Please use only numbers, letters, or underscore.',
                        ],
                    ],
                    [
                        'name'    => NoRecordExists::class,
                        'options' => [
                            'table'    => $this->userRoleEntitySpec->tableName,
                            'field'    => $this->userRoleEntitySpec->updateColumns['name'],
                            'adapter'  => $this->adapter,
                            'messages' => [
                                AbstractDb::ERROR_RECORD_FOUND
                                    => 'Role id already exists in database',
                            ],
                        ],
                    ],
                ],
            ],
            'isDefault' => [
                'required' => false,
            ],
            'parentId'  => [
                'required' => false,
                'filters'  => [
                    ['name' => ToNull::class],
                ],
            ],
        ];
    }
}
