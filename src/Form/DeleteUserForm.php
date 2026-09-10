<?php

namespace JUser\Form;

use Laminas\Db\Adapter\Adapter;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\CsrfSpec;

class DeleteUserForm extends Form implements InputFilterProviderInterface
{
    /**
     * The adapter the `RecordExists` validator below needs.
     *
     * Required, and passed in rather than read from
     * `GlobalAdapterFeature::getStaticAdapter()` as it was until 2026-08-14. That
     * registry was populated only by `JUser\Module::onBootstrap()`, so this form
     * threw `RuntimeException: No database adapter was found in the static registry`
     * for every input — benign included — in any process that had not booted
     * laminas-mvc: the Symfony kernel, a console command, a test harness.
     */
    public function __construct(private readonly Adapter $adapter, $name = null)
    {
        // we want to ignore the name passed
        parent::__construct('delete_user');

        $this->add([
            'name' => 'userId',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name' => 'delete',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Delete',
                'id' => 'submit',
                'class' => 'btn-danger'
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
            'security' => CsrfSpec::forElement($this->get('security')),
            'userId' => [
                'required' => true,
                'validators' => [
                    [
                        'name'    => 'Laminas\Validator\Db\RecordExists',
                        'options' => [
                            'table' => 'user',
                            'field' => 'user_id',
                            'adapter' => $this->adapter,
                            'messages' => [
                                \Laminas\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND =>
                                    'Assignment not found in database'
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
