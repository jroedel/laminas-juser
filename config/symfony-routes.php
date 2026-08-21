<?php

/**
 * This module's HTTP routes, described for a host to declare.
 *
 * A **fragment, not a route collection**: it returns a closure that calls back into
 * whatever the host uses to register a route. That indirection is the whole design.
 * Returning a `Symfony\Component\Routing\RouteCollection` would have been shorter and
 * would have forced every host into one routing library, one way of attaching
 * authorization, and one set of defaults — and on schoenstatt.link a ported route needs a
 * per-route ACL declaration and a translation text domain in its defaults, neither of
 * which this module knows anything about.
 *
 * ## The callback
 *
 *     $declare(
 *         string $name,                    // JUser\Routing\Routes names them
 *         string $path,                    // no locale prefix, no base URL
 *         string|array $controller,        // FQCN, or [FQCN, 'method']
 *         RouteAudience $audience,         // who may reach it
 *         array $defaults = [],            // route defaults this module needs
 *         array $requirements = []         // parameter constraints
 *     ): void
 *
 * The host is expected to add its own defaults on top — a text domain, a template
 * namespace, whatever its kernel needs — and to translate `$audience` into its own
 * authorization vocabulary. A host that already guards these routes by name may ignore the
 * audience; see {@see JUser\Routing\RouteAudience} on why it is declared anyway.
 *
 * ## Order
 *
 * As written, and it matters for any matcher that takes the first match: `/user` is a
 * prefix of the other three. They are all literal paths with no parameters, so no real
 * matcher would confuse them — but the order is free and being deliberate about it costs
 * nothing.
 *
 * ## What is not here
 *
 * The user-administration routes, which arrive with those controllers. And no route for
 * the cookie explainer, which is rendered *at* the two gated paths rather than at one of
 * its own — see JUser\Page\CookieExplainer.
 */

declare(strict_types=1);

use JUser\Controller\LogoutController;
use JUser\Controller\SignInController;
use JUser\Controller\VerifyController;
use JUser\Routing\RouteAudience;
use JUser\Routing\Routes;

return static function (callable $declare): void {
    //Nothing lives at /user; it bounces to the form, or to the host's post-login route
    //when the visitor already has an identity.
    $declare(Routes::INDEX, '/user', [SignInController::class, 'index'], RouteAudience::Anyone);

    //The form. GET renders it, POST issues and mails a link — and answers identically
    //whether or not the address is known, which is the property the page exists to have.
    $declare(Routes::LOGIN, '/user/login', [SignInController::class, 'form'], RouteAudience::Anyone);

    //Redemption: the one request that creates an authenticated session. `Anyone`, and it
    //has to be — the visitor is by definition not signed in yet.
    $declare(Routes::VERIFY, '/user/verify', VerifyController::class, RouteAudience::Anyone);

    //Signing out. The one route on this surface that needs an identity, so an anonymous
    //caller meets the host's sign-in redirect rather than being told "done".
    $declare(Routes::LOGOUT, '/user/logout', LogoutController::class, RouteAudience::SignedIn);
};
