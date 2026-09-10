<?php

namespace JUser\Form;

use SionModel\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\CsrfSpec;

/**
 * Mint an API token for one account.
 *
 * The only user input is a label, and the label is never signed, never sent to
 * the client and never used in a query — it exists so an admin can tell two of an
 * agent's tokens apart six months later. It is still filtered and length-bounded,
 * because the fuzz harness in the application's test/Fuzz asserts that every
 * element of every form under module/&#42;/src/Form/ carries a specification, and a
 * field that "obviously cannot hurt" is exactly the one that stops being obvious.
 */
class IssueApiTokenForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        parent::__construct('issue_api_token');

        $this->add([
            'name' => 'label',
            'type' => 'Text',
            'options' => [
                'label' => 'What is this token for?',
            ],
            'attributes' => [
                'maxlength' => 100,
                'placeholder' => 'e.g. nightly shrine enrichment',
                'class' => 'form-control',
            ],
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name' => 'issue',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Issue token',
                'id' => 'submit',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'label' => [
                //Optional: an unlabelled token is still a token, and refusing to
                //issue one over a missing note would be a strange place to be
                //strict.
                'required' => false,
                'filters' => [
                    ['name' => 'Laminas\Filter\StringTrim'],
                    ['name' => 'Laminas\Filter\StripTags'],
                    ['name' => 'Laminas\Filter\ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'Laminas\Validator\StringLength',
                        'options' => ['max' => 100],
                    ],
                ],
            ],
        ];
    }
}
