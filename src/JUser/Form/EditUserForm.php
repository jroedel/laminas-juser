<?php
namespace JUser\Form;

use Zend\Form\Form;
use Zend\Form\Element\Select;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class EditUserForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;

	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct ( 'user_edit' );

		$this->setAttribute ( 'method', 'post' );
		$this->add ( array (
			'name' => 'userId',
			'attributes' => array (
				'type' => 'hidden'
			)
		) );
		$this->add ( array (
			'name' => 'username',
			'attributes' => array (
				'type' => 'text',
				'size' => '30'
			),
			'options' => array (
				'label' => 'Username'
			)
		) );
		$this->add ( array (
			'name' => 'email',
			'attributes' => array (
				'type' => 'text',
				'size' => '50'
			),
			'options' => array (
				'label' => 'Email'
			)
		) );
		$this->add ( array (
			'name' => 'displayName',
			'attributes' => array (
				'type' => 'text',
				'size' => '50'
			),
			'options' => array (
				'label' => 'Display Name'
			)
		) );

		$this->add(array(
		    'name' => 'password',
		    'type' => 'Password',
		    'attributes' => array(
		        'required' => true,
		    ),
		    'options' => array(
		        'label' => 'Password',
		    ),
		));
		$this->add(array(
		    'name' => 'passwordVerify',
		    'type' => 'Password',
		    'attributes' => array(
		        'required' => true,
		    ),
		    'options' => array(
		        'label' => 'Verify Password',
		    ),
		));
		$this->add(array(
		    'name' => 'emailVerified',
		    'type' => 'Checkbox',
		    'options' => array(
		        'label' => 'Email verified',
		        'checked_value' => 1,
		        'unchecked_value' => 0,
		        'use_hidden_element' => false,
		    ),
		    'attributes' => array(
		        'value'           => 0,
		    ),
		));
		$this->add(array(
			'name' => 'active',
			'type' => 'Checkbox',
			'options' => array(
				'label' => 'Active',
                'checked_value' => 1,
                'unchecked_value' => 0,
                'use_hidden_element' => false,
			),
		    'attributes' => array(
		        'value'           => 0,
		    ),
		));
		$this->add ( array(
		    'name' => 'roles',
		    'type' => 'Select',
            'attributes' => array(
                'multiple' => 'multiple',
            ),
		    'options' => array(
			    'label' => 'Roles',
		    ),
		));
		$this->add(array(
			'name' => 'personId',
			'type' => 'Select',
			'options' => array(
				'label' => 'Person reference',
		        'empty_option' => '',
    			'disable_inarray_validator' => true,
			),
			'attributes' => array(
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

	public function setInputFilterSpecification($spec)
	{
	    $this->filterSpec = $spec;
	}

	public function getInputFilterSpecification()
	{
	    if ($this->filterSpec) {
	        return $this->filterSpec;
	    }
		$this->filterSpec = array(
			'userId' => array(
				'required' => true,
				'filters'  => array(
					array('name' => 'Int'),
                    array('name' => 'ToNull'),
				),
			),
			'username' => array(
				'required' => true,
				'filters'  => array(
					array('name' => 'StripTags'),
					array('name' => 'StringTrim'),
				),
				'validators' => array(
					array(
						'name'    => 'Regex',
						'options' => array(
							'pattern' => '/\A[0-9A-Za-z-_.]+\z/',
						),
                        'messages' => array(
                            Regex::INVALID => 'Please use only numbers, letters, dash, underscore or period.'
                        ),
					),
				),
			),
			'email' => array(
				'required' => true,
				'filters'  => array(
					array('name' => 'StringTrim'),
				),
                'validators' => array(
                    array(
                        'name' => 'EmailAddress',
                    ),
                ),
			),
			'displayName' => array(
				'required' => true,
				'filters'  => array(
					array('name' => 'StringTrim'),
				),
                'validators' => array(
                    array(
                        'name' => 'StringLength',
                        'options' => array(
                            'min' => 4,
                            'max' => 40
                        )
                    ),
                ),
			),
	        'personId' => array(
				'required' => false,
                'filters' => array(
                    array('name' => 'ToInt'),
                    array('name' => 'ToNull'),
                ),
			),
		    'emailVerified' => array(
		        'required' => false,
		        'filters' => array(
		            array('name' => 'Boolean'),
		        )
		    ),
		    'active' => array(
		        'required' => false,
		        'filters' => array(
		            array('name' => 'Boolean'),
		        )
		    ),
			'password' => array(
	            'required'   => false,
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
			'passwordVerify' => array(
	            'required'   => false,
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
	                        'token' => 'password'
	                    )
	                ),
	            ),
	            'filters'   => array(
	                array('name' => 'StringTrim'),
	            ),
	        )
		);
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
    	        $spec['displayName']['validators'][] = array(
                    'name'    => 'Zend\Validator\Db\NoRecordExists',
                    'options' => array(
                        'table' => 'user',
                        'field' => 'display_name',
                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                        'messages' => array(
                            \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND => 'Display name already exists in database'
                        ),
                    ),
                );
    	    }
    	    if ($spec && isset($spec['username']) && $spec['username']) {
    	        $spec['username']['validators'][] = array(
                    'name'    => 'Zend\Validator\Db\NoRecordExists',
                    'options' => array(
                        'table' => 'user',
                        'field' => 'username',
                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
                        'messages' => array(
                            \Zend\Validator\Db\NoRecordExists::ERROR_RECORD_FOUND => 'Username already exists in database'
                        ),
                    ),
    	        );
    	    }
	    } catch (\Exception $e) {

	    }
	    $this->setInputFilterSpecification($spec);
	}
}
