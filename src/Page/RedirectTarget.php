<?php

declare(strict_types=1);

namespace JUser\Page;

use JUser\Host\AccessInterface;
use JUser\Host\RouteResolverInterface;
use JUser\Model\User;

use function is_string;
use function parse_url;
use function str_starts_with;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Where a visitor was heading before they were asked to sign in, and whether they may
 * actually go there.
 *
 * ## This class lost two thirds of its size in the port, and that is the point
 *
 * The version this was ported from — `App\JUser\RedirectTarget` — carried a laminas
 * router, a laminas request, an ACL, a BjyAuthorize service, a locale-alias list and a
 * user table, and most of its 318 lines were the four hard-won details that made those
 * cooperate: strip the locale prefix before matching, match on a **clone** of the router
 * with an empty base URL, resolve the account's role *names* from the link table rather
 * than from `rolesList` (which holds numeric ids), and ask about the account's roles
 * rather than calling `isAllowed()`.
 *
 * Every one of those is a fact about one host's routing and authorization, and none of
 * them belongs in a module that is meant to drop into another application. They now live
 * behind {@see RouteResolverInterface} and {@see AccessInterface}, whose docblocks carry
 * the measurements, and what is left here is the part that is genuinely this module's: two
 * string rules about what a redirect target may look like, and the decision of what to do
 * with the two answers.
 *
 * **The details did not disappear and must not be lost.** A host implementing those two
 * interfaces has to reproduce them or this surface breaks in the exact way it broke
 * before: every destination refused, every visitor landed on the home page, and nothing
 * failing anywhere, because "no route" and "not allowed" are both legitimate answers.
 */
final class RedirectTarget
{
    public function __construct(
        private readonly RouteResolverInterface $routes,
        private readonly AccessInterface $access
    ) {
    }

    /**
     * A destination safe to send a browser to, or null.
     *
     * Three rules, and the **order matters**: the two string checks run before the host is
     * asked anything. That is not tidiness. Resolving a route is no defence against an
     * off-site destination and never was — a laminas router parses a URL and matches its
     * *path*, discarding the host, so both `//evil.example.com/` and
     * `https://evil.example.com/` resolve to a real route. Measured 2026-08-21. The
     * leading slash and the rejection of a protocol-relative `//host` are the entire
     * defence, they live here rather than in a host adapter for that reason, and a host
     * cannot weaken them by implementing its resolver differently.
     */
    public function valid(mixed $redirect): ?string
    {
        if (! is_string($redirect) || '' === $redirect) {
            return null;
        }
        //`//host/path` is a protocol-relative URL, i.e. an absolute one, and it starts
        //with a slash — so the first test does not imply the second.
        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return null;
        }

        return null === $this->routes->routeFor($redirect) ? null : $redirect;
    }

    /**
     * The route name behind $url when $user may **not** reach it, or null when they may.
     *
     * Null for a URL that resolves to no route as well, and that is right rather than
     * lazy: this is asked immediately after a successful sign-in, about a value that has
     * already been through {@see valid()}. "Nothing to refuse" and "nowhere to go" lead to
     * the same page.
     */
    public function refusedRoute(string $url, User $user): ?string
    {
        $route = $this->routes->routeFor($url);
        if (null === $route) {
            return null;
        }

        return $this->access->userMayReachRoute($user, $route) ? null : $route;
    }

    /**
     * The path and query of a URL, for telling a visitor where they were refused.
     *
     * Never the whole URL: an emailed link is absolute, so this may carry a scheme and
     * host, and a message quoting those reads like a phishing warning rather than a place
     * on this site.
     */
    public function pathOf(string $url): string
    {
        $path  = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($path) || '' === $path) {
            return $url;
        }

        return is_string($query) && '' !== $query ? $path . '?' . $query : $path;
    }
}
