<?php

declare(strict_types=1);

namespace JUser\Host;

use JUser\Model\User;

/**
 * Whether a given account may reach a given route.
 *
 * Note the shape: it asks about a **named account**, not about the current visitor.
 * That is unusual and it is the reason this interface exists at all rather than being
 * folded into the host's ordinary authorization check.
 *
 * ## Why an account rather than the visitor
 *
 * The one caller is the sign-in flow, deciding what to do with the `?redirect=` a
 * visitor arrived with: they are anonymous while the page renders and they are
 * *someone* a few lines later, in the same request. So "may I go there" has to be
 * asked on behalf of the account being signed in, at a moment when the request's
 * identity is still the previous one.
 *
 * On a laminas host that distinction is load-bearing rather than pedantic:
 * `BjyAuthorize\Service\Authorize::load()` runs once per request and bakes the
 * identity's roles into the ACL as it goes, and by this point the route guard has
 * already triggered that load while the visitor was anonymous. Asking
 * `isAllowed()` therefore answers for `guest` no matter who just signed in, and the
 * answer is "no" for every guarded page — so the visitor lands on the home page with
 * no explanation. `App\JUser\RedirectTarget` works around it by resolving the
 * account's own role names and consulting the ACL directly.
 *
 * A Symfony host has the same problem in a different dialect — a voter reads the token
 * from storage — and solves it the same way, by asking about a user object it is handed.
 *
 * ## What it replaces
 *
 * `BjyAuthorize\Service\Authorize` and the `Laminas\Permissions\Acl\Acl` behind it:
 * `bjy-authorize` and `laminas-permissions-acl` out of `require`. Which is as far as
 * this goes — **it does not remove bjy-authorize from a host that uses it.** Every
 * route a laminas host serves still needs a guard, and that guard is the host's.
 *
 * ## Default deny
 *
 * An implementation that cannot answer must answer `false`. A route with no rule, a
 * role the authorization layer has never heard of, a resource that does not exist:
 * all of them mean "nothing says yes", and this is a question asked *about a redirect*,
 * where the cost of a wrong `false` is a visitor landing on the home page and the cost
 * of a wrong `true` is one page briefly claiming to be reachable when it is not.
 */
interface AccessInterface
{
    /**
     * @param string $route a name {@see RouteResolverInterface::routeFor()} returned.
     *        The pairing is deliberate: this module never invents a route name to ask
     *        about, so a host is only ever asked about routes it declared itself.
     */
    public function userMayReachRoute(User $user, string $route): bool;

    /**
     * The ordinary question: may **whoever is making this request** reach that route?
     *
     * Asked to decide whether to draw a link, and only ever about a route this module
     * declared. Two links on the user index are behind it, on a page whose own audience is
     * already `Administrator` — which is not redundant, because a host's guard entries are
     * independent of each other: tightening the API-token route to a narrower role is a
     * supported thing to do, and the link has to disappear when it happens.
     *
     * Separate from {@see self::userMayReachRoute()} rather than folded into it, and the
     * separation is the honest one. This is what a host's ordinary authorization check
     * already answers — `isAllowed()`, a voter, a firewall — for the identity the request
     * carries. The other method asks about an account that is *not* the request's identity
     * yet, which is a question a host usually cannot answer without being handed the
     * account. Collapsing them would mean passing the current user in from this module,
     * and this module does not know how the host resolves roles for it.
     */
    public function visitorMayReachRoute(string $route): bool;
}
