<?php

declare(strict_types=1);

namespace JUser\Host;

use JUser\Model\User;

/**
 * Who is signed in, and the two operations that change the answer.
 *
 * ## Why identity is the host's and not this module's
 *
 * This module decides *whether* someone may sign in — it owns the token, the expiry,
 * the single-use redemption and the `user.state` check. What it does not own is where
 * "signed in" is recorded, and it must not: a host has exactly one identity, and every
 * other component asks the host for it. On schoenstatt.link that is a
 * `Laminas\Authentication\AuthenticationService` whose storage holds a user id; in a
 * Symfony application it is the security token storage. A module maintaining its own
 * would give a site two identities that can disagree, and the disagreement shows up as
 * a page that thinks you are anonymous next to a page that greets you by name.
 *
 * So this interface is the seam: this module says "this account has proved itself, make
 * it the identity", and reads back whatever the host considers current.
 *
 * ## What it replaces
 *
 * `Laminas\Authentication\AuthenticationService` — `hasIdentity()`, `getIdentity()`,
 * `getStorage()->write()` and `clearIdentity()`, between them the whole of this
 * module's use of `laminas-authentication`, which leaves `require`.
 *
 * `JUser\Authentication\Storage\SessionUser` does **not** leave: it is how a laminas
 * host implements this, and it carries the one property worth keeping — only the user
 * id is in the session, so the row is re-read every request, which is why deactivating
 * an account takes effect on the next request of a session that is already open rather
 * than at an invisible timeout. It moves to `JUser\Bridge\Laminas\` with the rest of the
 * laminas-shaped code, and a host implementing {@see self::current()} over something
 * that caches a user object for the life of the session loses that property. Say so
 * where you do.
 */
interface IdentityInterface
{
    /**
     * The signed-in account, or null.
     *
     * Null must also be the answer for an account that exists but may no longer act —
     * `user.state = 0`. See the note above on why this is asked per request.
     */
    public function current(): ?User;

    /**
     * Make this account the identity of the current session.
     *
     * Called once, at the moment a sign-in link is successfully redeemed, and after
     * {@see SessionInterface::regenerateId()} — see that method for why the order is
     * the defence rather than an implementation detail.
     *
     * This module has already checked everything it knows how to check by the time it
     * calls this: the token matched, was unexpired and unspent, and the account is
     * active. An implementation is not expected to re-authenticate, and must not refuse
     * silently; a host that cannot establish an identity should throw, because the
     * alternative is a visitor who clicked a valid link, spent their token, and is
     * still anonymous with nothing on screen saying why.
     */
    public function establish(User $user): void;

    /**
     * Sign out: forget the identity, keep the session.
     *
     * The session is dealt with separately and afterwards — {@see
     * SessionInterface::forgetMe()} then {@see SessionInterface::regenerateId()} — so
     * this method is only about the identity record.
     */
    public function clear(): void;
}
