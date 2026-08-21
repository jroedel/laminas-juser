<?php

declare(strict_types=1);

namespace JUser\Controller;

use Exception;
use JTranslate\I18n\TranslatableMessage;
use JUser\Form\IssueApiTokenForm;
use JUser\Form\RevokeApiTokenForm;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Model\User;
use JUser\Page\UserAdmin;
use JUser\Routing\Routes;
use JUser\Service\ApiTokenService;
use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_array;
use function is_int;
use function is_string;

/**
 * The API-token screen: `/users/{user_id}/api-tokens` and its POST-only revoke twin.
 *
 * Two routes, two methods, because the second is not a view of the first: it takes a
 * `{token_id}` of its own and answers nothing but a redirect.
 *
 * This is the only screen in this module that mints a credential, and the three things that
 * make that safe are properties of the code rather than of the page:
 *
 * 1. **`mayIssueForUser()` is re-checked on the POST**, not merely consulted to decide
 *    whether to draw a button. The GET decides what to render; this decides whether to mint
 *    a months-long bearer token, and a hand-crafted POST never saw the view at all.
 * 2. **Revocation is scoped by account as well as token.** `revoke($tokenId, $id, …)`, so a
 *    token id belonging to another account cannot be revoked from this account's page —
 *    which is also why "already revoked" is an *info* message rather than an error: the
 *    honest reading of a second submit is a double-click.
 * 3. **The freshly issued JWT never enters the message pipeline.** It travels in a one-shot
 *    session slot, read and cleared by the render that follows the POST. See
 *    {@see UserAdmin::takeIssuedToken()} for what that avoids, which is not hypothetical.
 */
final class ApiTokensController
{
    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly UrlBuilderInterface $urls
    ) {
    }

    /**
     * GET|POST — the screen, and the issue button.
     *
     * @throws TwigError
     */
    public function screen(Request $request): Response
    {
        $user = $this->admin->user($request);
        if (null === $user) {
            $this->admin->flash(Severity::Error, 'User not found.');

            return new RedirectResponse($this->urls->path(Routes::USERS), Response::HTTP_FOUND);
        }
        $id = (int) $user['userId'];

        $tokens   = $this->admin->apiTokens();
        $mayIssue = $tokens->mayIssueForUser($id);
        $form     = new IssueApiTokenForm();

        if ($request->isMethod('POST')) {
            $this->issue($request, $tokens, $form, $id, $user, $mayIssue);

            return $this->toScreen($id);
        }

        //Read and cleared in one act: the token is shown on exactly the render that follows
        //its issuance, and a refresh of that page must not show it again.
        $justIssued = $this->admin->takeIssuedToken();

        return new Response($this->twig->render(JUserExtension::template('api-tokens'), [
            'page_title'     => 'API Tokens',
            'user_id'        => $id,
            'user'           => $user,
            'display_name'   => $this->displayName($user),
            'tokens'         => $tokens->getTokensForUser($id),
            'form'           => $form,
            'revoke_form'    => new RevokeApiTokenForm(),
            'may_issue'      => $mayIssue,
            'issuable_roles' => $tokens->getIssuableRoles(),
            'lifetime_days'  => $tokens->getLifetimeDays(),
            'just_issued'    => $justIssued,
            'self_url'       => $this->urls->path(Routes::API_TOKENS, ['user_id' => $id]),
        ]));
    }

    /** POST only — revoke one token. A non-POST is answered with the redirect and nothing else. */
    public function revoke(Request $request): Response
    {
        //Deliberately *not* UserAdmin::user(): everything this needs is the pair of ids, and
        //the service checks that they belong together. Loading the account would add a query
        //and a second not-found path to a route that only ever redirects.
        /** @var mixed $rawUser */
        $rawUser = $request->attributes->get('user_id');
        /** @var mixed $rawToken */
        $rawToken = $request->attributes->get('token_id');
        $id       = is_string($rawUser) || is_int($rawUser) ? (int) $rawUser : 0;
        $tokenId  = is_string($rawToken) || is_int($rawToken) ? (int) $rawToken : 0;

        if (! $request->isMethod('POST')) {
            return $this->toScreen($id);
        }

        $form = new RevokeApiTokenForm();
        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->admin->flash(Severity::Error, 'That request expired, please try again.');

            return $this->toScreen($id);
        }

        if ($this->admin->apiTokens()->revoke($tokenId, $id, $this->admin->actingUserId())) {
            $this->admin->flash(Severity::Success, 'Token revoked. It stops working on its next request.');
        } else {
            $this->admin->flash(
                Severity::Info,
                'That token was already revoked, or does not belong to this account.'
            );
        }

        return $this->toScreen($id);
    }

    /**
     * The POST branch of the screen. Reports its outcome as a flash and returns nothing:
     * every path ends in the same redirect.
     *
     * @param array<string, mixed> $user
     */
    private function issue(
        Request $request,
        ApiTokenService $tokens,
        IssueApiTokenForm $form,
        int $id,
        array $user,
        bool $mayIssue
    ): void {
        if (! $mayIssue) {
            $this->admin->flash(Severity::Error, 'This account may not be issued an API token.');

            return;
        }

        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->admin->flash(Severity::Error, 'Error in form submission, please review.');

            return;
        }

        /** @var mixed $data */
        $data = $form->getData();
        /** @var mixed $label */
        $label = is_array($data) ? ($data['label'] ?? null) : null;
        $label = is_string($label) ? $label : null;

        try {
            /** @var mixed $token */
            $token = $tokens->issue(new User($user), $label, $this->admin->actingUserId());
        } catch (Exception $e) {
            $this->admin->logger()?->error('JUser: Failed to issue an API token.', [
                'userId'    => $id,
                'exception' => $e,
            ]);
            //A TranslatableMessage, so the exception text is a *parameter* and not part of
            //the phrase key — one row for the sentence rather than one per distinct failure.
            //Same reasoning as the token itself, one step weaker: an unbounded string in a
            //translated message is a phrase-table row per value.
            $this->admin->flash(
                Severity::Error,
                new TranslatableMessage('Could not issue a token: %s', [$e->getMessage()])
            );

            return;
        }

        /** @var mixed $jwt */
        $jwt = is_array($token) ? ($token['jwt'] ?? null) : null;
        if (is_string($jwt)) {
            $this->admin->rememberIssuedToken(['jwt' => $jwt, 'label' => $label]);
        }

        $this->admin->flash(Severity::Success, 'Token issued.');
    }

    /**
     * The first of displayName, username, email that is non-empty.
     *
     * @param array<string, mixed> $user
     */
    private function displayName(array $user): string
    {
        foreach (['displayName', 'username', 'email'] as $field) {
            /** @var mixed $value */
            $value = $user[$field] ?? null;
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return '';
    }

    private function toScreen(int $id): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->path(Routes::API_TOKENS, ['user_id' => $id]),
            Response::HTTP_FOUND
        );
    }
}
