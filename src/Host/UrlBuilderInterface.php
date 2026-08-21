<?php

declare(strict_types=1);

namespace JUser\Host;

/**
 * Builds a URL for a route the **host** owns, from inside this module.
 *
 * Every page this module serves links to pages it does not: the sign-in form redirects
 * to whatever `juser.login_redirect_route` names, the user list links to a person, and
 * the emailed sign-in link names `zfcuser/verify`. None of those routes are declared
 * here, and none of them can be — a route name means nothing without a router, and
 * which router that is, is exactly what this module has stopped deciding.
 *
 * ## What it replaces
 *
 * `Laminas\Router\RouteStackInterface::assemble()`, reached three different ways in
 * 2.x: through the `url` view helper in a .phtml, through `App\Laminas\RouteUrl` in
 * every ported controller, and directly inside `JUser\Service\Mailer`, which holds a
 * router of its own for the one link it has to make absolute. All three become this,
 * which is what takes `laminas-router` out of `require`.
 *
 * ## Why the locale prefix is not this interface's business
 *
 * On schoenstatt.link an assembled path carries a locale segment (`/en/users`), put
 * there by SlmLocale setting the router's base URL. That is a property of the host's
 * routing, not of a URL builder, and a host that has no locales implements the same
 * two methods and returns paths without one. This module never inspects, strips or
 * appends a prefix — see {@see RouteResolverInterface}, which is the other half of the
 * same reasoning and the place the prefix does have to be understood.
 */
interface UrlBuilderInterface
{
    /**
     * A root-relative path, e.g. `/en/users/12/edit`.
     *
     * @param string $route a route name the host recognises
     * @param array<string, mixed> $params route parameters, e.g. `['user_id' => 12]`
     * @param array<string, string> $query appended as a query string; already-decoded
     *        values, encoded by the implementation
     */
    public function path(string $route, array $params = [], array $query = []): string;

    /**
     * The same, absolute: scheme, host and path.
     *
     * Needed for exactly one thing, and it is worth naming because it is the reason
     * this method is not "nice to have": the **emailed sign-in link**. A relative path
     * in an email is not a link at all, and a link built on the wrong host sends a
     * single-use token somewhere the visitor cannot redeem it.
     *
     * `JUser\Service\Mailer` asked its own router for this with `force_canonical`, and
     * on a Symfony-served request got a router no locale listener had touched — so the
     * link came out unprefixed, one 302 away from the page that redeems it, which a
     * mail scanner does not follow. Measured 2026-08-21. Handing the job to the host
     * makes that a property of one implementation rather than of whichever router
     * happened to be injected.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $query
     */
    public function url(string $route, array $params = [], array $query = []): string;
}
