<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Form\LoginForm;
use JUser\Host\IdentityInterface;
use JUser\Host\UrlBuilderInterface;
use JUser\Page\CookieExplainer;
use JUser\Page\RedirectTarget;
use JUser\Page\SignIn;
use JUser\Routing\Routes;
use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_string;

/**
 * `/user` and `/user/login` — the front door.
 *
 * Two routes and one class, because `/user` exists only to bounce a visitor to the form
 * and answering "where does the front door go" twice would be worse than one branch.
 *
 * ## The response is identical whether or not the address is known
 *
 * The single most important property of this page, and it is achieved by
 * {@see SignIn::issueAndSend()} swallowing every outcome rather than by anything here: an
 * unknown address is registered, a known one gets a link, a deactivated one gets nothing, a
 * throttled one gets nothing, and the mailer throwing is logged and dropped. All five
 * render the same "check your email" page. Anything added to {@see form()} that reports
 * what happened turns the form into an account oracle.
 *
 * ## The destination travels twice, deliberately
 *
 * In the session *and* in the emailed link — see {@see SignIn::rememberDestination()} for
 * which of the two is the optimisation and which is the guarantee.
 * {@see VerifyController} prefers whichever arrived.
 *
 * ## The consent gate
 *
 * Checked before anything else, and it renders the explainer **at this URL with a 200**
 * rather than redirecting — see {@see SignIn::wantsCookiesFirst()} for why that matters to
 * an emailed link.
 */
final class SignInController
{
    public function __construct(
        private readonly SignIn $signIn,
        private readonly RedirectTarget $targets,
        private readonly CookieExplainer $explainer,
        private readonly Environment $twig,
        private readonly UrlBuilderInterface $urls,
        private readonly IdentityInterface $identity
    ) {
    }

    /** GET /user — nothing lives here. */
    public function index(): RedirectResponse
    {
        return null !== $this->identity->current()
            ? $this->toRoute($this->signIn->loginRedirectRoute())
            : $this->toRoute(Routes::LOGIN);
    }

    /**
     * GET /user/login renders the form; POST issues and mails a link.
     *
     * @throws TwigError
     */
    public function form(Request $request): Response
    {
        if ($this->signIn->wantsCookiesFirst($request)) {
            return $this->explainer->response();
        }

        if (null !== $this->identity->current()) {
            return $this->toRoute($this->signIn->loginRedirectRoute());
        }

        $form = new LoginForm();

        $redirect = $this->targets->valid($request->query->get('redirect'));
        if (null !== $redirect) {
            $form->get('redirect')->setValue($redirect);
        }

        if (! $request->isMethod('POST')) {
            return $this->renderForm($form);
        }

        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            return $this->renderForm($form);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        /** @var mixed $email */
        $email = $data['email'] ?? null;
        if (! is_string($email) || '' === $email) {
            //unreachable through the input filter, which requires the field and validates
            //it as an address; belt and braces because everything below assumes a string
            return $this->renderForm($form);
        }

        $redirect = $this->targets->valid($data['redirect'] ?? null);
        if (null !== $redirect) {
            $this->signIn->rememberDestination($redirect);
        }

        $this->signIn->issueAndSend($email, $redirect);

        return new Response($this->twig->render(JUserExtension::template('check-email'), [
            'page_title'         => 'Check your email',
            'email'              => $email,
            'expiration_minutes' => $this->signIn->tokens()->getWebTokenExpirationMinutes(),
        ]));
    }

    /** @throws TwigError */
    private function renderForm(LoginForm $form): Response
    {
        return new Response($this->twig->render(JUserExtension::template('login'), [
            //the raw string: a `headTitle()` view helper translates what it holds when it
            //renders, so passing an already-translated value made the page translate its
            //own output and file the result as a phrase — "Registrati" and "Registrar"
            //became keys in their own right. The layout translates this.
            'page_title' => 'Sign in',
            'form'       => $form,
        ]));
    }

    private function toRoute(string $route): RedirectResponse
    {
        return new RedirectResponse($this->urls->path($route), Response::HTTP_FOUND);
    }
}
