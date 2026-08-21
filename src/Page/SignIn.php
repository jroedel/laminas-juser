<?php

declare(strict_types=1);

namespace JUser\Page;

use JTranslate\I18n\TranslatableMessage;
use JUser\Host\FlashInterface;
use JUser\Host\SessionInterface;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Routing\Routes;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function is_array;
use function is_string;

/**
 * What all four sign-in routes need, in one place.
 *
 * Ported from the consuming application's `App\JUser\SignIn`, and the port is where it
 * changed shape. That class was a set of lazy accessors over `App\Laminas\ServiceBridge` —
 * a laminas ServiceManager built per request — because a Symfony-served controller had no
 * other way to reach a laminas service. Here every collaborator is a constructor
 * argument, which is the whole difference between a module that can be dropped into
 * another application and one that can only run next to a laminas container.
 *
 * The accessors survive that change and are worth keeping: they are what lets the three
 * controllers read line-for-line like the ones this was ported from, on the one surface of
 * this module where the evidence that nothing changed matters more than the tidiness of
 * the seam. They can collapse into direct dependencies later; nothing depends on them
 * staying.
 *
 * ## Nothing here is authorization
 *
 * Three of the four routes admit anonymous visitors — a page you visit *in order to* sign
 * in cannot require an identity — and the fourth admits any account. So the route audience
 * is the whole of it and there is nothing for a controller to check, unlike
 * {@see UserAdmin}'s surface. The one gate below is not authorization.
 *
 * ## The consent gate, and why this module has one at all
 *
 * A visitor who has not consented to cookies cannot be signed in: there is no session to
 * write an identity into, and a host that strips cookies off an unconsented response
 * strips the CSRF token with it, so a form served here would fail on submission with
 * nothing to show for it. The two entry points therefore ask {@see wantsCookiesFirst()}
 * before doing anything and answer with {@see CookieExplainer} — **a 200 at the requested
 * URL, not a redirect**, so an emailed link survives being consented to.
 *
 * It is off unless a host names a cookie. A consent gate is site policy, not this
 * module's, and a host with no such policy gets no gate rather than a gate that reads a
 * cookie nobody sets — which would refuse every sign-in on the site.
 */
final class SignIn
{
    /**
     * Where the post-sign-in destination is remembered.
     *
     * Both strings are what `JUser\Controller\LoginController` writes, and they must stay
     * that way while it exists: it is the rollback path for this surface, so a visitor can
     * ask for a link under one front controller and click it under the other. Two
     * different slots would lose the destination silently, and only for the visitors
     * caught across the flip.
     */
    public const SESSION_NAMESPACE = 'JUser';
    public const DESTINATION       = 'redirect';

    /** Where a signed-in visitor goes when they ask for a sign-in page. */
    public const DEFAULT_LOGIN_REDIRECT_ROUTE = 'welcome';

    /**
     * @param string|null $consentCookie the cookie a host sets when a visitor consents to
     *        cookies; null means the host has no consent gate and the explainer is never
     *        served. Read off the request rather than `$_COOKIE` so it is testable.
     * @param string $consentValue what that cookie holds when consent has been given
     * @param string $loginRedirectRoute where a visitor lands after signing in, and where
     *        an already-signed-in visitor is sent if they ask for the form. A host route,
     *        which is why it is configurable and why the default is only a sensible guess.
     * @param string $logoutRedirectRoute where signing out lands. Defaults to this
     *        module's own login route, which needs no host cooperation.
     */
    public function __construct(
        private readonly UserTable $users,
        private readonly LoginTokenService $tokens,
        private readonly Mailer $mailer,
        private readonly UrlBuilderInterface $urls,
        private readonly SessionInterface $session,
        private readonly FlashInterface $messages,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $consentCookie = null,
        private readonly string $consentValue = 'true',
        private readonly string $loginRedirectRoute = self::DEFAULT_LOGIN_REDIRECT_ROUTE,
        private readonly string $logoutRedirectRoute = Routes::LOGIN
    ) {
    }

    /** Has this visitor consented to cookies? True when the host has no gate. */
    public function hasConsented(Request $request): bool
    {
        if (null === $this->consentCookie) {
            return true;
        }

        return $this->consentValue === $request->cookies->get($this->consentCookie);
    }

    /**
     * Whether this request must be answered with the cookie explainer instead of the page
     * it asked for. See the class docblock.
     */
    public function wantsCookiesFirst(Request $request): bool
    {
        return ! $this->hasConsented($request);
    }

    public function table(): UserTable
    {
        return $this->users;
    }

    public function tokens(): LoginTokenService
    {
        return $this->tokens;
    }

    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /** The route a signed-in visitor is sent to. */
    public function loginRedirectRoute(): string
    {
        return $this->loginRedirectRoute;
    }

    /** The route signing out lands on. */
    public function logoutRedirectRoute(): string
    {
        return $this->logoutRedirectRoute;
    }

    /**
     * Mail the sign-in link.
     *
     * **The link is assembled here, from the host's URL builder**, and that is a change
     * from 2.x worth knowing about because the old arrangement failed in production.
     * `Mailer` held a router of its own and assembled the link with `force_canonical` — and
     * under a Symfony dispatch that router was the raw container one, which no locale
     * listener had touched, so it produced `/user/verify?token=…` with no locale prefix:
     * the unprefixed twin of the real route, which answers a 302. A browser survives that
     * hop; a mail client that pre-fetches, a scanner, or anything that does not follow it
     * does not, and the token is single-use. Measured 2026-08-21.
     *
     * Asking the host for an absolute URL removes the whole class of problem: there is one
     * URL builder in the application and the emailed link comes off the same one as every
     * link on every page.
     */
    public function sendLoginLink(User $user, string $plaintextToken, ?string $redirect = null): void
    {
        $query = ['token' => $plaintextToken];
        if (null !== $redirect && '' !== $redirect) {
            $query['redirect'] = $redirect;
        }

        $this->mailer->sendLoginLink(
            $user,
            $this->urls->url(Routes::VERIFY, [], $query),
            $this->tokens->getWebTokenExpirationMinutes()
        );
    }

    /**
     * Look the address up, registering it if it is new, and mail a link.
     *
     * **Every outcome produces the same answer to the caller**, and that is the property
     * the sign-in form exists to have rather than a detail of this method: an unknown
     * address is registered and mailed, a known one is mailed, a deactivated one is not, a
     * throttled one is not, and a mailer that throws is logged and dropped. Anything added
     * here that reports which happened turns the form into an account oracle. It is
     * therefore `void`, deliberately, so a caller cannot branch on it even by accident.
     */
    public function issueAndSend(string $email, ?string $redirect): void
    {
        try {
            $user = $this->users->findByEmail($email);
            if (! $user instanceof User) {
                //open registration: an unknown address simply becomes an account, created
                //active and unverified — see UserTable::createUserFromEmail()
                $created = $this->users->createUserFromEmail($email);
                if (! is_array($created)) {
                    return;
                }
                $user = new User($created);
            }

            //A deactivated account is sent nothing, silently. `state` means "may sign in"
            //and the answer here must not distinguish it from any other outcome.
            if (1 != $user->getState()) {
                $this->logger?->info(
                    'JUser: Declined to issue a sign-in link for a deactivated account.',
                    ['userId' => $user->getId()]
                );

                return;
            }

            if (! $this->tokens->mayIssueToken($user)) {
                $this->logger?->info('JUser: Throttled a sign-in link request.', ['userId' => $user->getId()]);

                return;
            }

            $this->sendLoginLink($user, $this->tokens->issueWebToken($user), $redirect);
        } catch (Throwable $e) {
            $this->logger?->error('JUser: Failed to issue a sign-in link.', ['exception' => $e]);
        }
    }

    /**
     * Remember where the visitor was going, in the session as well as in the link.
     *
     * The session is the better channel when it survives — nothing about the destination
     * is then visible or editable — but it only survives when the link is opened in the
     * browser that asked for it, and the ordinary case is asking on a desktop and clicking
     * on a phone. So the session is the optimisation and the link is the guarantee.
     */
    public function rememberDestination(string $redirect): void
    {
        $this->session->set(self::SESSION_NAMESPACE, self::DESTINATION, $redirect);
    }

    /** The remembered destination, removed as it is read. Null when there is none. */
    public function takeDestination(): ?string
    {
        /** @var mixed $remembered */
        $remembered = $this->session->get(self::SESSION_NAMESPACE, self::DESTINATION);
        if (null === $remembered) {
            return null;
        }

        $this->session->remove(self::SESSION_NAMESPACE, self::DESTINATION);

        return is_string($remembered) ? $remembered : null;
    }

    /**
     * Read by the *next* page, which is what this surface wants: every message it produces
     * accompanies a redirect. Nothing here needs a now-message.
     *
     * @param string|TranslatableMessage $message
     */
    public function flash(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages->flash($severity, $message);
    }
}
