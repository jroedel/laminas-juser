<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationServiceInterface;
use SionModel\Service\ActingUserProviderInterface;

/**
 * Resolves the acting user id from 'JUser\AuthService' at call time.
 *
 * The AuthenticationService is looked up lazily on first call — resolving it
 * during construction would re-form the UserTable/ProblemService/ProblemTable/
 * AuthService dependency cycle. The id itself is never cached: a user can log
 * in mid-request (magic-link redemption), and every call must observe the
 * current identity.
 */
class AuthServiceActingUserProvider implements ActingUserProviderInterface
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var AuthenticationServiceInterface|null $authService */
    private $authService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getActingUserId(): ?int
    {
        if (null === $this->authService) {
            $this->authService = $this->container->get('JUser\AuthService');
        }
        $identity = $this->authService->getIdentity();
        if (! $identity) {
            return null;
        }
        return (int) $identity->id;
    }
}
