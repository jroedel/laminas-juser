<?php

declare(strict_types=1);

namespace JUser\Filter;

use Laminas\Crypt\Password\Bcrypt;
use Laminas\Filter\AbstractFilter;
use LmcUser\Options\ModuleOptions;

class HashPasswordForLmcUser extends AbstractFilter
{
    private Bcrypt $bcrypt;

    public function __construct(private ModuleOptions $lmcModuleOptions)
    {
    }

    /**
     * @inheritDoc
     */
    public function filter($value)
    {
        return $this->getBcrypt()->create($value);
    }

    public function getBcrypt(): Bcrypt
    {
        if (isset($this->bcrypt)) {
            return $this->bcrypt;
        }
        $this->bcrypt = new Bcrypt();
        $this->bcrypt->setCost($this->lmcModuleOptions->getPasswordCost());
        return $this->bcrypt;
    }
}
