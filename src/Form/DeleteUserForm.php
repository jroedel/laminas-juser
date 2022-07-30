<?php

declare(strict_types=1);

namespace JUser\Form;

use Laminas\Form\Element\Button;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Submit;
use Laminas\Form\Form;

class DeleteUserForm extends Form
{
    public function __construct()
    {
        // we want to ignore the name passed
        parent::__construct('delete_user');

        $this->add([
            'name' => 'security',
            'type' => Csrf::class,
        ]);
        $this->add([
            'name'       => 'delete',
            'type'       => Submit::class,
            'attributes' => [
                'value' => 'Delete',
                'id'    => 'submit',
                'class' => 'btn-danger',
            ],
        ]);
        $this->add([
            'name'       => 'cancel',
            'type'       => Button::class,
            'attributes' => [
                'value'        => 'Cancel',
                'id'           => 'cancel',
                'data-dismiss' => 'modal',
            ],
        ]);
    }
}
