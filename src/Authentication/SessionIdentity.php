<?php

declare(strict_types=1);

namespace JUser\Authentication;

use Closure;
use JUser\Host\IdentityInterface;
use JUser\Host\SessionInterface;
use JUser\Model\User;
use JUser\Model\UserTable;

use function is_int;
use function is_string;

/**
 * {@see IdentityInterface} over the host's session: the user id is stored, the account is
 * re-read from the database on every request.
 *
 * ## Why this is here, when the interface says identity belongs to the host
 *
 * It still does. This is a *default* a host may replace, and the reason to ship one is
 * that the alternative was a laminas host: until 2026-09 this was
 * `Laminas\Authentication\AuthenticationService` wrapping a storage adapter, and the
 * module's `require` carried `laminas-authentication` for it. An application built on
 * Symfony Security registers its own implementation and this class is never constructed.
 * What a host must not do is keep a *second* identity beside the one it already has.
 *
 * ## Only the id is stored, so a deactivation takes effect on the next request
 *
 * This is the property {@see IdentityInterface::current()} asks for, and the one thing
 * about this class worth preserving in any reimplementation. The session holds an integer.
 * Every read resolves it against the `user` table and checks `state`, so an administrator
 * revoking a compromised account does not wait on a session timeout they can neither see
 * nor influence. It is the fourth and last place `user.state` is enforced, and the only
 * one that reaches a session that is already open — the other three all happen at the
 * door: no link is mailed, a link in flight is refused, a bearer token is refused.
 *
 * The cost is one indexed lookup per request, of a row the page was going to need anyway.
 *
 * ## The user table arrives as a closure, and an anonymous request never opens it
 *
 * The session is read first, and most requests have nothing in it. Taking `UserTable`
 * eagerly meant building it — with its database adapter, its cache and its dependents — on
 * every anonymous page view: measured at 1.9 ms of the auth layer's 3.2 ms, spent to look
 * up nobody. The closure is resolved on the first request that actually has an id to
 * resolve.
 *
 * ## The stored value is memoized, and establishing clears the memo
 *
 * A page asks who is signed in many times. Resolving once per request is the point of the
 * memo; dropping it in {@see establish()} is what keeps a magic-link redemption — which
 * signs someone in *mid-request* — from being answered with the anonymous state read
 * moments earlier.
 *
 * ## The session namespace is `Laminas_Auth`, and that is not an oversight
 *
 * It is where `Laminas\Authentication\Storage\Session` put the id, and every session
 * currently open on schoenstatt.link has it there. Renaming it would sign every visitor
 * out on deploy, for nothing. See {@see self::SESSION_NAMESPACE}.
 */
final class SessionIdentity implements IdentityInterface
{
    /**
     * The namespace `Laminas\Authentication\Storage\Session` defaulted to, and the key it
     * used inside it. Kept verbatim: a live session holds the id under these two strings,
     * and a host deploying this release should not sign everybody out to gain a nicer name.
     */
    public const SESSION_NAMESPACE = 'Laminas_Auth';
    public const SESSION_KEY       = 'storage';

    private ?User $resolved = null;
    private bool $haveResolved = false;

    /** @var Closure(): UserTable */
    private Closure $users;

    /** @param Closure(): UserTable $users resolved on the first request that has an id to look up */
    public function __construct(
        private readonly SessionInterface $session,
        Closure $users
    ) {
        $this->users = $users;
    }

    public function current(): ?User
    {
        if ($this->haveResolved) {
            return $this->resolved;
        }
        $this->haveResolved = true;

        $id = $this->session->get(self::SESSION_NAMESPACE, self::SESSION_KEY);
        if (! is_int($id) && ! (is_string($id) && '' !== $id)) {
            return $this->resolved = null;
        }

        $user = ($this->users)()->findById($id);
        if (! $user instanceof User || 1 !== (int) $user->getState()) {
            //A stored id that no longer resolves to an account allowed to act is not a
            //temporary condition: drop it, so the next request is anonymous rather than
            //repeating a lookup whose answer cannot change without a new sign-in.
            $this->session->remove(self::SESSION_NAMESPACE, self::SESSION_KEY);

            return $this->resolved = null;
        }

        return $this->resolved = $user;
    }

    public function establish(User $user): void
    {
        $this->session->set(self::SESSION_NAMESPACE, self::SESSION_KEY, (int) $user->getId());
        $this->haveResolved = false;
        $this->resolved     = null;
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_NAMESPACE, self::SESSION_KEY);
        $this->haveResolved = false;
        $this->resolved     = null;
    }
}
