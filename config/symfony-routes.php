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
 * No route for the cookie explainer, which is rendered *at* the two gated sign-in paths
 * rather than at one of its own — see JUser\Page\CookieExplainer. It had a route once, for
 * years, with no guard entry: default deny made the URL unreachable from the day it was
 * written and the page was only ever reached by a laminas route-match swap that named no
 * route at all.
 */

declare(strict_types=1);

use JUser\Controller\ApiTokensController;
use JUser\Controller\LogoutController;
use JUser\Controller\SignInController;
use JUser\Controller\UserCreateController;
use JUser\Controller\UserDeleteController;
use JUser\Controller\UserEditController;
use JUser\Controller\UsersController;
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

    // -----------------------------------------------------------------------------------
    // User administration. All seven are `Administrator`, and on a host where that maps to
    // a role real administrators hold and nobody else does, the audience is the *whole*
    // protection: no controller on this surface carries a second check. Said out loud
    // because the absence of one is what a reader will look for.

    $declare(Routes::USERS, '/users', UsersController::class, RouteAudience::Administrator);

    //The two create forms. One controller, told which record to make by a route default,
    //because the two differ in the form they build and the message they flash and in
    //nothing else worth a second class.
    $declare(
        Routes::USER_CREATE,
        '/users/create',
        UserCreateController::class,
        RouteAudience::Administrator,
        [UserCreateController::KIND => UserCreateController::USER]
    );
    $declare(
        Routes::ROLE_CREATE,
        '/users/roles/create',
        UserCreateController::class,
        RouteAudience::Administrator,
        [UserCreateController::KIND => UserCreateController::ROLE]
    );

    //One account's form. Renders the delete modal, which posts to the next route.
    //
    //The literal create paths above come first, and one of them needs to: `/users/roles/create`
    //is three segments and so is `/users/{user_id}/api-tokens`. The `[0-9]{1,5}` constraint is
    //what keeps `roles` out of `user_id`, so the constraint and not the order is doing the
    //work — but a constraint is something a host can widen, and a reader should not have to
    //check one to know what `/users/roles/create` does.
    $declare(
        Routes::USER_EDIT,
        '/users/{user_id}/edit',
        UserEditController::class,
        RouteAudience::Administrator,
        [],
        ['user_id' => '[0-9]{1,5}']
    );

    //GET renders a confirmation, POST deletes. Both verbs on one route and **no method
    //constraint**: adding one would turn a mistaken GET into a 405 where today it renders the
    //page.
    $declare(
        Routes::USER_DELETE,
        '/users/{user_id}/delete',
        UserDeleteController::class,
        RouteAudience::Administrator,
        [],
        ['user_id' => '[0-9]{1,5}']
    );

    //The credential screen and its revoke twin. Two methods on one controller rather than two
    //controllers, because the second is not a view of the first: it takes a `token_id` of its
    //own, answers nothing but a redirect, and shares only the account it scopes to.
    $declare(
        Routes::API_TOKENS,
        '/users/{user_id}/api-tokens',
        [ApiTokensController::class, 'screen'],
        RouteAudience::Administrator,
        [],
        ['user_id' => '[0-9]{1,5}']
    );
    $declare(
        Routes::API_TOKEN_REVOKE,
        '/users/{user_id}/api-tokens/{token_id}/revoke',
        [ApiTokensController::class, 'revoke'],
        RouteAudience::Administrator,
        [],
        ['user_id' => '[0-9]{1,5}', 'token_id' => '[0-9]+']
    );
};
