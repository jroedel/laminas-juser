<?php

namespace JUser\Bridge\Laminas;

use Laminas\Authentication\AuthenticationService;
use Laminas\View\Helper\AbstractHelper;

/**
 * Returns the authenticated user, or false when anonymous.
 *
 * Keeps the historical 'zfcUserIdentity' helper name.
 */
class ZfcUserIdentity extends AbstractHelper
{
    /** @var AuthenticationService $authService */
    protected $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @return \JUser\Model\User|false
     */
    public function __invoke()
    {
        if ($this->getAuthService()->hasIdentity()) {
            return $this->getAuthService()->getIdentity();
        }
        return false;
    }

    /**
     * @return AuthenticationService
     */
    public function getAuthService()
    {
        return $this->authService;
    }

    /**
     * @param AuthenticationService $authService
     * @return self
     */
    public function setAuthService(AuthenticationService $authService)
    {
        $this->authService = $authService;
        return $this;
    }
}
