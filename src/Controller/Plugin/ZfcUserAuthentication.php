<?php

namespace JUser\Controller\Plugin;

use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Controller plugin proxying the application AuthenticationService.
 *
 * Keeps the historical 'zfcUserAuthentication' plugin name so that existing
 * controllers keep working after the removal of ZfcUser.
 */
class ZfcUserAuthentication extends AbstractPlugin
{
    /** @var AuthenticationService $authService */
    protected $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Proxy convenience method
     *
     * @return bool
     */
    public function hasIdentity()
    {
        return $this->getAuthService()->hasIdentity();
    }

    /**
     * Proxy convenience method
     *
     * @return \JUser\Model\User|null
     */
    public function getIdentity()
    {
        return $this->getAuthService()->getIdentity();
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
