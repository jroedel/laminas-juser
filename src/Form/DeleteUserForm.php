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
		
		$this->add(array(
			'name' => 'userId',
			'type' => 'Hidden',
		));
		$this->add(array(
			'name' => 'security',
			'type' => 'csrf',
		));
		$this->add(array(
			'name' => 'delete',
			'type' => 'Submit',
			'attributes' => array(
				'value' => 'Delete',
				'id' => 'submit',
				'class' => 'btn-danger'
			),
		));
		$this->add(array(
			'name' => 'cancel',
 			'type' => 'Button',
			'attributes' => array(
				'value' => 'Cancel',
				'id' => 'cancel',
				'data-dismiss' => 'modal'
			),
		));
	}
	
	public function getInputFilterSpecification()
	{
		return array(
			'userId' => array(
				'required' => true,
	            'validators' => array(
	                array(
	                    'name'    => 'Zend\Validator\Db\RecordExists',
	                    'options' => array(
	                        'table' => 'user',
	                        'field' => 'user_id',
	                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
	                        'messages' => array(
	                            \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'Assignment not found in database' 
	                        ),
	                    ),
	                ),
	            ),
			),
		);
	}
}