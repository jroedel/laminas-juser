<?php

namespace JUser\Bridge\Laminas;

use Psr\Container\ContainerInterface;
use JUser\Model\UserTable;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Session\ManagerInterface as SessionManagerInterface;

/**
 * Builds the application's AuthenticationService. There is no adapter: the only
 * way to obtain an identity is by redeeming an emailed login token, which writes
 * the user id straight into the storage.
 */
class AuthenticationServiceFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var UserTable $userTable */
        $userTable = $container->get(UserTable::class);

        $sessionManager = null;
        if ($container->has(SessionManagerInterface::class)) {
            $sessionManager = $container->get(SessionManagerInterface::class);
        }
        $sessionStorage = new SessionStorage(null, null, $sessionManager);

        return new AuthenticationService(new SessionUser($userTable, $sessionStorage));
    }
}
