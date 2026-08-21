<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Host\IdentityInterface;
use JUser\Host\SessionInterface;
use JUser\Host\UrlBuilderInterface;
use JUser\Page\SignIn;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/user/logout` — sign out.
 *
 * The three calls are all of it, and each does something the other two do not: clearing the
 * identity empties the record of who was signed in, forgetting drops any remember-me
 * lifetime off the session cookie, and regenerating with `$destroyOld` issues a new session
 * id and **deletes the old one server-side**, so the id that was authenticated a moment
 * ago cannot be replayed.
 *
 * Their order is the reverse of {@see VerifyController}'s and for the same reason: the
 * session id being retired should be the last one that was ever authenticated.
 *
 * Declared for {@see \JUser\Routing\RouteAudience::SignedIn}, i.e. any signed-in account.
 * An anonymous visitor asking for it should meet the host's sign-in redirect rather than
 * be quietly told "done".
 *
 * **No consent gate here**, unlike the other two entry points, and that is deliberate
 * rather than an omission: signing *out* needs no cookie to be set — it needs one removed
 * — and a visitor who never consented has no session to end anyway.
 */
final class LogoutController
{
    public function __construct(
        private readonly SignIn $signIn,
        private readonly UrlBuilderInterface $urls,
        private readonly IdentityInterface $identity,
        private readonly SessionInterface $session
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        $this->identity->clear();
        $this->session->forgetMe();
        $this->session->regenerateId(true);

        return new RedirectResponse(
            $this->urls->path($this->signIn->logoutRedirectRoute()),
            Response::HTTP_FOUND
        );
    }
}
