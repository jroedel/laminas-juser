<?php

namespace JUser\Bridge\Laminas;

use JUser\Model\User;
use Laminas\Authentication\AuthenticationService;
use Laminas\View\Helper\AbstractHelper;

/**
 * Returns a display name for the given (or authenticated) user, false when anonymous.
 *
 * Keeps the historical 'zfcUserDisplayName' helper name.
 */
class ZfcUserDisplayName extends AbstractHelper
{
    /** @var AuthenticationService $authService */
    protected $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param User|null $user
     * @return string|false
     */
    public function __invoke($user = null)
    {
        if (null === $user) {
            if (! $this->getAuthService()->hasIdentity()) {
                return false;
            }
            $user = $this->getAuthService()->getIdentity();
        }
        if (! $user instanceof User) {
            return false;
        }

        $displayName = $user->getDisplayName();
        if (null === $displayName || '' === $displayName) {
            $displayName = $user->getUsername();
        }
        if (null === $displayName || '' === $displayName) {
            $displayName = (string) $user->getEmail();
            $atPosition = strpos($displayName, '@');
            if (false !== $atPosition) {
                $displayName = substr($displayName, 0, $atPosition);
            }
        }

        return $displayName;
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
