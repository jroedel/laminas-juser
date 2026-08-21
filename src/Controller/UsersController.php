<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Host\AccessInterface;
use JUser\Page\UserAdmin;
use JUser\Routing\Routes;
use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * GET /users — the user-administration index.
 *
 * Every account, its roles, and the person record it points at, with a pencil to the edit
 * form and a crown to the API-token screen beside each username.
 *
 * ## The two icons are permission-gated, and that is not redundant
 *
 * This page's own audience is already `Administrator`, so a reader may wonder why the links
 * are checked at all. Because a host's guard entries are independent of one another:
 * narrowing the API-token route to a tighter role is a supported thing to do, and the crown
 * has to vanish when it happens.
 *
 * **Asked once per page rather than once per row**, unlike the laminas view it came from,
 * which called `isAllowed()` inside the loop. Same answer — the question does not mention
 * the row — and on a host that reaches an ACL or a voter for it, 292 accounts is 584 calls
 * saved.
 *
 * ## What the page costs
 *
 * `UserTable::getUsers()` loads every account, links its roles in one further query, and
 * caches the whole result. Measured on the application this was ported from: **292
 * accounts, 0.017s, 0.55 MiB serialized**, and a 144 KB page. An earlier measurement said
 * 6,303 accounts and 14.03 MiB — an artefact of that application's own test fixtures, not
 * of the data, and worth remembering as a shape of mistake rather than as a number.
 */
final class UsersController
{
    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly AccessInterface $access
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        return new Response($this->twig->render(JUserExtension::template('users-index'), [
            'page_title'        => 'User Management',
            'users'             => $this->admin->table()->getUsers(),
            //null when this host configures no person provider, which the template's own
            //guard reads as "render the column empty"
            'persons'           => $this->admin->persons(),
            'may_edit'          => $this->access->visitorMayReachRoute(Routes::USER_EDIT),
            'may_issue_tokens'  => $this->access->visitorMayReachRoute(Routes::API_TOKENS),
        ]));
    }
}
