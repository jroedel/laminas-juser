<?php

namespace JUser\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\CsrfSpec;

/**
 * Revoke one API token.
 *
 * Carries nothing but a CSRF token: which token to revoke comes from the route,
 * not the body, so a submitted form cannot name a different one than the button
 * the admin pressed. The controller then scopes the revocation by user id as
 * well, so even a hand-crafted request cannot reach across accounts.
 *
 * A POST rather than a link, because a GET that revokes a credential is one
 * prefetching browser extension away from doing it by itself.
 */
class RevokeApiTokenForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        parent::__construct('revoke_api_token');

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
        ]);
        $this->add([
            'name' => 'revoke',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Revoke',
                'class' => 'btn btn-danger btn-xs',
            ],
        ]);
    }

    /**
     * Only the CSRF token: `submit` is a button rather than data, and this form carries
     * no fields of its own.
     *
     * `security` is stated here even though `Laminas\Form\Element\Csrf` supplies the
     * validator itself. That was the reasoning this docblock used to give for returning
     * an empty array, and it stops being safe at step 5:
     * `SionModel\Form\Validation\InputFilter` reads the specification and nothing else,
     * so a check that exists only on the element is a check the cutover removes. See
     * SionModel\Form\CsrfSpec.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
        ];
    }
}
