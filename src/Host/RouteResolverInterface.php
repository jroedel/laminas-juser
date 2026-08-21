<?php

declare(strict_types=1);

namespace JUser\Host;

/**
 * Which of the host's routes a path belongs to, if any.
 *
 * Asked about exactly one kind of value: a `?redirect=` carried into the sign-in flow,
 * i.e. a string a visitor may have edited. Two answers are wanted from it — "is this a
 * place on this site at all" (a destination that resolves to no route is not a
 * destination) and "which route, so that {@see AccessInterface} can be asked whether
 * this account may go there".
 *
 * ## What it replaces
 *
 * `RouteStackInterface::match()` against a hand-built `Laminas\Http\Request`, in
 * `LoginController::matchUrl()`. That is the last use of `laminas-router` and the only
 * use of `laminas-http` in this module, so this one method takes both out of `require`.
 *
 * ## The prefix problem is the host's, and this is why
 *
 * Under laminas-mvc the router only ever saw `/shrines`, because SlmLocale had already
 * stripped `/en` off the request and set the router's base. Under a Symfony dispatch no
 * such listener runs, so the same router sees `/en/shrines` and matches nothing —
 * measured 2026-08-21 on every route on the site. A `?redirect=` always carries the
 * prefix, so a faithful transcription of `matchUrl()` refused **every** destination and
 * sent every visitor to the home page after signing in. Nothing failed: "no route"
 * is a legitimate answer, and the defect is invisible in a log.
 *
 * That is a fact about one host's routing, and the reason this interface takes a path
 * rather than a router: whatever a host has to strip, alias or normalise before it can
 * answer, it does behind this method. `App\JUser\RedirectTarget` already does exactly
 * that, including the detail that makes it correct — it matches on a *clone* of the
 * router with an empty base URL, because the shared one's base is mutated by whatever
 * assembled a link earlier in the request.
 *
 * ## What it must not be used for
 *
 * **Resolving a route is no defence against an off-site destination.** A laminas
 * request parses a URL and the router matches its *path*, discarding the host, so both
 * `//evil.example.com/` and `https://evil.example.com/` resolve to the route `welcome`
 * — measured. The rejection of an absolute or protocol-relative URL happens in this
 * module, before anything is asked here, and stays there.
 */
interface RouteResolverInterface
{
    /**
     * The route name a path resolves to, or null.
     *
     * @param string $path root-relative, and it may carry a query string — a route can
     *        match on one, and dropping it here would answer a different question from
     *        the one the caller asked. Malformed input is a null answer, not an
     *        exception: every caller is holding a value a visitor could have typed.
     */
    public function routeFor(string $path): ?string;
}
