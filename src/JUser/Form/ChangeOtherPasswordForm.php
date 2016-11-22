<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class ChangeOtherPasswordForm extends Form implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('change_other_password');
		
		$this->add(array(
			'name' => 'userId',
			'type' => 'Hidden',
		));
		$this->add(array(
			'name' => 'newCredential',
			'type' => 'Password',
			'attributes' => array(
            	'required' => true,
			),
			'options' => array(
				'label' => 'Password',
			),
		));
		$this->add(array(
			'name' => 'newCredentialVerify',
			'type' => 'Password',
			'attributes' => array(
            	'required' => true,
			),
			'options' => array(
				'label' => 'Verify Password',
			),
		));
		$this->add(array(
			'name' => 'security',
			'type' => 'csrf',
		));
		$this->add(array(
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => array(
				'value' => 'Change Password',
				'id' => 'submit',
				'class' => 'btn-primary'
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
	                        'table' => 'a_data_role_assignment',
	                        'field' => 'AssignmentId',
	                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
	                        'messages' => array(
	                            \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'Assignment not found in database' 
	                        ),
	                    ),
	                ),
	            ),
			),
			'newCredential' => array(
	            'name'       => 'newCredential',
	            'required'   => true,
	            'validators' => array(
	                array(
	                    'name'    => 'StringLength',
	                    'options' => array(
	                        'min' => 4,
	                    ),
	                ),
	            ),
	            'filters'   => array(
	                array('name' => 'StringTrim'),
	            ),
	        ),
			'newCredentialVerify' => array(
	            'name'       => 'newCredentialVerify',
	            'required'   => true,
	            'validators' => array(
	                array(
	                    'name'    => 'StringLength',
	                    'options' => array(
	                        'min' => 4,
	                    ),
	                ),
	                array(
	                    'name' => 'identical',
	                    'options' => array(
	                        'token' => 'newCredential'
	                    )
	                ),
	            ),
	            'filters'   => array(
	                array('name' => 'StringTrim'),
	            ),
	        )
		);
	}
}