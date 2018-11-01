<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class DeleteUserForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
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
            'userId' => [
                'required' => true,
                'validators' => [
                    [
                        'name'    => 'Zend\Validator\Db\RecordExists',
                        'options' => [
                            'table' => 'user',
                            'field' => 'user_id',
                            'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                            'messages' => [
                                \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND =>
                                    'Assignment not found in database'
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
