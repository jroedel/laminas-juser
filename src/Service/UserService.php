<?php

namespace JUser\Service;

use Laminas\Authentication\AuthenticationService;

/**
 * Backwards compatibility shim for consumers that were written against
 * ZfcUser\Service\User and only ever call ->getAuthService().
 *
 * New code should depend on the AuthenticationService ('JUser\AuthService') directly.
 */
class UserService
{
    /** @var AuthenticationService $authService */
    protected $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @return AuthenticationService
     */
    public function getAuthService()
    {
        return $this->authService;
    }
}
