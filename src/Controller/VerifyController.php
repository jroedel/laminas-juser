<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Host\IdentityInterface;
use JUser\Host\SessionInterface;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Model\User;
use JUser\Page\CookieExplainer;
use JUser\Page\RedirectTarget;
use JUser\Page\SignIn;
use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_string;
use function sprintf;
use function trim;

/**
 * `GET /user/verify?token=…` — redeem a sign-in link.
 *
 * **The one request that creates an authenticated session**, which is why every branch
 * below is spelled out rather than collapsed.
 *
 * ## Order of operations, and none of it is arbitrary
 *
 *  1. **consent first.** No cookies means no session to write the identity into, so the
 *     explainer is served at this URL with a 200 and the token is left unspent — the link
 *     still works once the visitor consents.
 *  2. **redeem, which burns the token.** `redeemToken()` refuses an unknown, malformed or
 *     expired token and consumes a valid one in the same call.
 *  3. **refuse a deactivated account.** After the burn, deliberately: answering the
 *     question needs the row, and the row is what `redeemToken()` looks up. Spending a
 *     rejected credential is the right outcome anyway.
 *  4. **regenerate the session id, then establish the identity.** Session fixation: an
 *     attacker who fixed the id before this holds an authenticated session after it,
 *     unless the id changes at the moment the privilege level does. The two calls are
 *     spelled out here rather than folded into
 *     {@see IdentityInterface::establish()} so that the ordering is visible at the point
 *     that depends on it.
 *  5. **check the destination while there is still a page to say it on** — see below.
 *
 * ## Signed in, and not allowed where you were going
 *
 * Two different facts, and the second used to silently eat the first: a visitor who asked
 * for an admin page without an account got a link, clicked it, was signed in, was bounced
 * by the route guard back to the sign-in page — which, now that they had an identity,
 * redirected them onward. Three redirects, no explanation, and the natural reading is that
 * the link did not work.
 *
 * So the destination is checked here and reported with **two** messages, one good and one
 * bad, because collapsing them loses the half the visitor needs most: that they *are*
 * signed in and need not try the link again — they could not, it is single-use.
 *
 * Both messages come from one {@see \JUser\Host\FlashInterface}, which is why the host
 * must hold one instance per request: on a laminas host a second flash-messenger moves the
 * first's message out of the session and drops it, and this is the page that measured it.
 */
final class VerifyController
{
    public function __construct(
        private readonly SignIn $signIn,
        private readonly RedirectTarget $targets,
        private readonly CookieExplainer $explainer,
        private readonly Environment $twig,
        private readonly UrlBuilderInterface $urls,
        private readonly IdentityInterface $identity,
        private readonly SessionInterface $session
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        if ($this->signIn->wantsCookiesFirst($request)) {
            return $this->explainer->response();
        }

        /** @var mixed $token */
        $token = $request->query->get('token');
        if (! is_string($token) || '' === trim($token)) {
            return $this->failed();
        }

        $user = $this->signIn->tokens()->redeemToken($token);
        if (! $user instanceof User) {
            return $this->failed();
        }

        if (1 != $user->getState()) {
            $this->signIn->logger()?->notice(
                'JUser: Refused a login link for a deactivated account.',
                ['userId' => $user->getId()]
            );

            return $this->deactivated();
        }

        //new privilege level, new session id — in that order
        $this->session->regenerateId(true);
        $this->identity->establish($user);

        $this->signIn->logger()?->info(
            'JUser: A user signed in with a login link.',
            ['userId' => $user->getId()]
        );

        $destination = $this->destination($request);

        $refused = $this->targets->refusedRoute($destination, $user);
        if (null !== $refused) {
            $this->signIn->logger()?->info(
                'JUser: Signed a user in, but their destination was not permitted.',
                ['userId' => $user->getId(), 'route' => $refused]
            );
            $this->signIn->flash(Severity::Success, 'You are signed in.');
            $this->signIn->flash(Severity::Error, sprintf(
                'Your account does not have access to %s, so we have brought you here instead.',
                $this->targets->pathOf($destination)
            ));

            return $this->toLoginRedirect();
        }

        return new RedirectResponse($destination, Response::HTTP_FOUND);
    }

    /**
     * Where to send a freshly signed-in visitor: the link's own `?redirect=`, else the one
     * remembered in the session, else the configured post-login route.
     *
     * The link's copy is validated **again** here, and not because it was not validated
     * when it was issued: between there and here it was a query parameter in an email.
     * The session's copy is validated too — it was a query parameter once as well.
     */
    private function destination(Request $request): string
    {
        $redirect = $this->targets->valid($request->query->get('redirect'));
        if (null === $redirect) {
            $redirect = $this->targets->valid($this->signIn->takeDestination());
        }

        return $redirect ?? $this->urls->path($this->signIn->loginRedirectRoute());
    }

    /** @throws TwigError */
    private function failed(): Response
    {
        return new Response(
            $this->twig->render(JUserExtension::template('verify-failed'), [
                'page_title' => "This sign-in link didn't work",
            ]),
            Response::HTTP_BAD_REQUEST
        );
    }

    /** @throws TwigError */
    private function deactivated(): Response
    {
        return new Response(
            $this->twig->render(JUserExtension::template('deactivated'), [
                'page_title' => 'This account has been deactivated',
            ]),
            Response::HTTP_FORBIDDEN
        );
    }

    private function toLoginRedirect(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->path($this->signIn->loginRedirectRoute()),
            Response::HTTP_FOUND
        );
    }
}
