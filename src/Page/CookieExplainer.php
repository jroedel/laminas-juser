<?php

declare(strict_types=1);

namespace JUser\Page;

use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * The "you need cookies to sign in" page, served **at whatever URL asked for it**.
 *
 * **A 200 at the requested URL, not a redirect**, and the distinction is the reason this
 * is a class rather than each controller redirecting somewhere. A redirect would rewrite
 * the address bar of someone who had just clicked an emailed link, and the whole point of
 * the gate is that the link is still good once they consent: a token is spent only on a
 * successful redemption.
 *
 * On schoenstatt.link this reproduces `Application\View\GdprStrategy::onRoute()`, which
 * swapped the *route match* of the two gated routes for this page. That listener runs on
 * `MvcEvent::EVENT_ROUTE` and so does not run at all for a route served by a Symfony
 * kernel — and its swap ran at priority -5000, i.e. after the guard had already approved a
 * different route, which is how the page it swapped in was rendered without ever being
 * authorized. Asking {@see SignIn::wantsCookiesFirst()} first lands in the same place with
 * none of that: same URL, same status, same template.
 *
 * There is deliberately **no route** for this page, here or anywhere. Nothing links to it,
 * and a URL that renders it would be a URL a host has to guard.
 */
final class CookieExplainer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * `page_title` is passed **empty rather than omitted**: a layout that reads it
     * unguarded under `strict_variables` — this module's own host does — turns an omission
     * into a fatal, and a fatal inside a rendered response arrives as a 200 with an empty
     * body rather than as a 500. Cheap to pass, expensive to debug.
     *
     * @throws TwigError
     */
    public function response(): Response
    {
        return new Response($this->twig->render(
            JUserExtension::template('sign-in-no-cookies'),
            ['page_title' => '']
        ));
    }
}
