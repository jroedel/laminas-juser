<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\Form\Element\Select;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class CreateRoleForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;
    
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct ( 'role_create' );
		
		$this->setAttribute ( 'method', 'post' );
		$this->add ( array (
			'name' => 'name',
			'attributes' => array (
				'type' => 'text',
				'size' => '30' 
			),
			'options' => array (
				'label' => 'Role name' 
			)
		) );
		
		$this->add ( array(
		    'name' => 'parentId',
		    'type' => 'Select',
		    'options' => array(
			    'label' => 'Parent',
		        'empty_option' => '',
		        'unselected_value' => '',
		    ),
		));

		$this->add(array(
			'name' => 'security',
			'type' => 'csrf',
		));
		$this->add ( array (
			'name' => 'submit',
			'attributes' => array (
			    'class' => 'btn-primary',
				'type' => 'submit',
				'value' => 'Submit',
				'id' => 'submit' 
			) 
		) );
	}

	public function getInputFilterSpecification()
	{
	    return array(
			'name' => array(
				'required' => true,
				'filters'  => array(
					array('name' => 'StripTags'),
					array('name' => 'StringTrim'),
				),
				'validators' => array(
					array(
						'name'    => 'Regex',
						'options' => array(
							'pattern' => '/\A[0-9A-Za-z_]+\z/',
						),
                        'messages' => array(
                            Regex::INVALID => 'Please use only numbers, letters, or underscore.'
                        ),
					),
				    array(
                        'name'    => 'Zend\Validator\Db\NoRecordExists',
                        'options' => array(
                            'table' => 'user_role',
                            'field' => 'role_id',
                            'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                            'messages' => array(
                                \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND => 'Role id already exists in database'
                            ),
                        ),
                    ),
				),
			),
			'parentId' => array(
				'required' => false,
				'filters'  => array(
					array('name' => 'ToNull'),
				),
			),
		);
	}
}
