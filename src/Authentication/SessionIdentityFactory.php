<?php

declare(strict_types=1);

namespace JUser\Authentication;

use JUser\Host\SessionInterface;
use JUser\Model\UserTable;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Builds {@see SessionIdentity}, which needs the host's session and this module's user
 * table and nothing else.
 *
 * The session is the host's to provide — see {@see SessionInterface} on why a module that
 * starts its own session is a module that writes a second cookie — so a host that has
 * registered no `JUser\Host\SessionInterface` gets a clear error here rather than an
 * identity that silently forgets everyone at the end of each request.
 */
class SessionIdentityFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName = SessionIdentity::class,
        ?array $options = null
    ): SessionIdentity {
        if (! $container->has(SessionInterface::class)) {
            throw new RuntimeException(
                'JUser needs a host session: register JUser\Host\SessionInterface. '
                . 'Without one there is nowhere to record who is signed in.'
            );
        }
        /** @var SessionInterface $session */
        $session = $container->get(SessionInterface::class);

        //The table is passed as a closure, not resolved here: most requests are anonymous,
        //and building UserTable for them costs a database adapter and a cache to look up
        //nobody. See SessionIdentity's docblock for the measurement.
        return new SessionIdentity($session, static function () use ($container): UserTable {
            /** @var UserTable $users */
            $users = $container->get(UserTable::class);

            return $users;
        });
    }
}
