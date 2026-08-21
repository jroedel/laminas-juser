<?php

declare(strict_types=1);

namespace JUser\Routing;

/**
 * Who a route in this module's route fragment is meant to admit.
 *
 * Three cases, because this module's routes need exactly three answers, and it says them
 * in its own vocabulary rather than in any host's. A host reading the fragment maps them
 * onto whatever it has: an ACL resource, a Symfony Security access-control rule, a
 * firewall. The mapping is the host's because the *names* are: on schoenstatt.link
 * `administrator` happens to be a role in a database table, and `lib_user` looks like a
 * role and admits everybody signed in.
 *
 * ## Why the fragment carries this at all
 *
 * Because a route that loses its guard keeps working. That is the whole hazard of a
 * strangler migration and it has already happened on this site: of 191 routes, 149
 * restrict access, and a route ported without its guard published itself while looking
 * perfectly healthy. A fragment that shipped paths and controllers but left the audience
 * implicit would hand every host the same trap — and the sign-in surface is the worst
 * possible place for it, since `Anyone` and `SignedIn` are one line apart here and the
 * difference between them is whether signing out is a public URL.
 *
 * A host that already guards these routes by name — schoenstatt.link does; its guard
 * entries predate the port — may legitimately ignore the value. It is still worth
 * declaring: the guard entries are what would have to be *reconstructed* by anyone else,
 * and this is the only place they are written down in terms that survive leaving laminas.
 */
enum RouteAudience
{
    /**
     * Anonymous visitors included, and it has to be: a page you visit *in order to* sign
     * in cannot require an identity. Three of this module's four sign-in routes are this.
     */
    case Anyone;

    /**
     * Any authenticated account, with no further check. Signing out is the example: an
     * anonymous caller should meet the sign-in redirect rather than be told "done".
     */
    case SignedIn;

    /**
     * An account trusted to administer other accounts.
     *
     * The user-administration routes, and the one audience where the host's mapping really
     * matters — on schoenstatt.link this is the only non-default role involved in this
     * module, held by 2 of 292 accounts, and it is the entire protection of those pages.
     */
    case Administrator;
}
