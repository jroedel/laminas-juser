<?php

namespace JUser\Form;

use SionModel\Form\Form;
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
     * `security` is stated here because nothing else states it. `Laminas\Form\Element\Csrf`
     * used to supply the validator itself, which was this docblock's reason for returning
     * an empty array; `SionModel\Form\Validation\InputFilter` reads the specification and
     * nothing else, and since the element swap `SionModel\Form\Element\Csrf` supplies no
     * input specification at all — it owns the token and says nothing about validating it.
     * See SionModel\Form\CsrfSpec, which writes the rule this line reads.
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
