<?php

declare(strict_types=1);

namespace JUser\Routing;

/**
 * The names of the routes this module declares.
 *
 * Constants rather than literals because three different things have to agree on each
 * string — the route fragment that declares it, the code that builds a link to it, and
 * the host's guard entry — and a mismatch between the first two is a dead link while a
 * mismatch with the third silently changes who may reach the page.
 *
 * ## Why they still say `zfcuser`
 *
 * ZfcUser has not been installed here for years; JUser replaced it with its own
 * passwordless controller. What survives is the *names*, and they survive on purpose:
 * renaming a route breaks every `url()` call, every `laminas_path()` and every guard
 * entry that names it, across the consuming application as well as this module. A rename
 * is a coordinated change to code nobody has a reason to touch, in exchange for tidier
 * strings — so the strings stay, and this docblock is the explanation a reader is owed.
 *
 * The paths do not have the same problem and were never `zfcuser`-shaped: `/user`,
 * `/user/login`, `/user/verify`, `/user/logout`.
 */
final class Routes
{
    /** `/user` — bounces to {@see self::LOGIN} or to the host's post-login route. */
    public const INDEX = 'zfcuser';

    /** `/user/login` — the form. */
    public const LOGIN = 'zfcuser/login';

    /** `/user/verify` — redemption, and the URL that goes in the email. */
    public const VERIFY = 'zfcuser/verify';

    /** `/user/logout` */
    public const LOGOUT = 'zfcuser/logout';
}
