<?php

declare(strict_types=1);

namespace JUser\Host;

/**
 * The session, as much of it as this module needs: a namespaced scratch space and two
 * operations that happen at a privilege change.
 *
 * ## What it replaces
 *
 * `Laminas\Session\Container` — two of them, `JUser` for the post-sign-in destination
 * and `JUser\ApiToken` for a freshly issued JWT on its way to the page that displays it
 * — and the `Laminas\Session\SessionManager` methods below. That is `laminas-session`
 * out of `require`.
 *
 * **Starting the session is not on this interface.** Something has to have done it
 * before a controller runs: on schoenstatt.link that is `App\Http\SessionListener`, on
 * a Symfony host it is the session listener in the kernel. A module that started the
 * session itself would be deciding cookie parameters, storage and lifetime on the
 * host's behalf, which is how two components end up writing two session cookies.
 *
 * ## Why a namespace argument rather than a service per container
 *
 * Two callers, two namespaces, and one of them is read by a *different* request from
 * the one that wrote it. Keeping the namespace in the call means the two strings live
 * next to the code that owns them instead of in the host's wiring, where a rename would
 * look harmless and would strand whatever was mid-flow.
 */
interface SessionInterface
{
    /** Null when nothing is stored, which is indistinguishable from a stored null. */
    public function get(string $namespace, string $key): mixed;

    public function set(string $namespace, string $key, mixed $value): void;

    public function remove(string $namespace, string $key): void;

    /**
     * Issue a new session id.
     *
     * Called immediately **before** an identity is established and again after it is
     * cleared, which is the whole of this module's session-fixation defence: an attacker
     * who fixed the id beforehand must not be holding an authenticated session
     * afterwards. `$destroyOld` is what makes it a defence rather than a gesture — the
     * old id has to stop working server-side, not merely stop being sent.
     *
     * This module calls it explicitly rather than leaving it to
     * {@see IdentityInterface::establish()} so that the ordering is visible in the
     * controller that depends on it. A host whose identity layer migrates the session
     * on its own (Symfony Security does) will regenerate twice, which is harmless.
     */
    public function regenerateId(bool $destroyOld = true): void;

    /**
     * Stop this session outliving the browser session.
     *
     * `Laminas\Session\SessionManager::forgetMe()` — it puts the session cookie's
     * lifetime back to 0, undoing a remember-me. Called on sign-out, before
     * {@see self::regenerateId()}, so that the id being retired is also the last one
     * with a persistent cookie.
     *
     * A host with no remember-me mechanism may implement this as a no-op, and should
     * say so where it does: "nothing to forget" and "we forgot to implement it" look
     * identical from here.
     */
    public function forgetMe(): void;
}
