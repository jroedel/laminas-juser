<?php

declare(strict_types=1);

namespace JUser\Page;

use JTranslate\I18n\TranslatableMessage;
use JUser\Form\DeleteUserForm;
use JUser\Host\FlashInterface;
use JUser\Host\SessionInterface;
use JUser\Host\Severity;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Model\UserTable;
use JUser\Service\ApiTokenService;
use Laminas\Db\Adapter\Adapter;
use SionModel\Form\FormInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use SionModel\Service\ActingUserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function ctype_digit;
use function get_class;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function ucfirst;

/**
 * What all five controllers of the user-administration surface share.
 *
 * The counterpart of {@see SignIn} for the other half of this module, and deliberately
 * plumbing rather than policy: the table, the forms, the person list, the acting user, the
 * two messengers and one log-and-say-so helper.
 *
 * ## There is no refuse() here, and that is worth saying out loud
 *
 * All seven routes are declared {@see \JUser\Routing\RouteAudience::Administrator}, and on
 * a host where that maps to a role real administrators hold and nobody else does, the
 * route audience really is the whole protection — so there is nothing left for a
 * controller to check.
 *
 * That is the *unusual* case. On schoenstatt.link, where this was ported from, four of the
 * roles that guards name are `is_default = 1`, so "restricted to `lib_user`" means "signed
 * in" and the real check has to live inside the page. A reader arriving from that surface
 * will look for the missing check here; the answer is that `administrator` is not a default
 * role — 2 of 292 accounts hold it.
 *
 * ## Why the forms come out of a container and not the constructor
 *
 * `EditUserForm` is registered as a **shared** service by every host that follows this
 * module's config, and both `setValidatorsForCreate()` and `prepareForEdit()` mutate the
 * instance they are called on. A form captured in a controller's constructor would be one
 * object for the life of the process, so the create page would hand the edit page a form
 * still carrying the create-only uniqueness validators.
 *
 * Fetching per call reproduces what a per-request container does. {@see form()} is the
 * single place that happens, so the hazard has one place to be documented and one place to
 * change if it ever needs a fresh instance rather than a shared one. The container is
 * PSR-11 and is only ever asked for form ids — a form locator, not a service locator.
 */
final class UserAdmin
{
    /** See {@see takeIssuedToken()} on why this is not the sign-in flow's namespace. */
    public const TOKEN_NAMESPACE = 'JUser\ApiToken';
    public const TOKEN_SLOT      = 'issued';

    /**
     * @param ContainerInterface $forms a locator for this module's form services; see the
     *        class docblock on why it is a container and what it may be asked for
     * @param PersonValueOptionsProviderInterface|null $persons the host's link between an
     *        account and a person record. Null is ordinary: this module has no person model
     *        and a host that has none either renders the column empty.
     * @param ActingUserProviderInterface|null $actingUser who is performing the request,
     *        for the provenance columns. Resolved per call rather than captured, because
     *        identity must never be read while a container is still building.
     */
    public function __construct(
        private readonly UserTable $users,
        private readonly ContainerInterface $forms,
        private readonly Adapter $adapter,
        private readonly ApiTokenService $apiTokens,
        private readonly SessionInterface $session,
        private readonly FlashInterface $messages,
        private readonly ?PersonValueOptionsProviderInterface $persons = null,
        private readonly ?ActingUserProviderInterface $actingUser = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function table(): UserTable
    {
        return $this->users;
    }

    /**
     * One of this module's form services. See the class docblock on the shared-instance
     * hazard, which is the whole reason this is a method.
     *
     * `string` rather than `class-string`: the create controller reads the id out of a
     * route-keyed table, which static analysis sees as a plain string, and asserting the
     * narrower type at the call site would be a claim that table cannot make.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function form(string $formId): FormInterface
    {
        /** @var FormInterface<array<string, mixed>> $form */
        $form = $this->forms->get($formId);

        return $form;
    }

    /**
     * A delete form, built fresh.
     *
     * Never shared and never cached, unlike {@see form()}'s services: a form carries the
     * data and the validation messages of whatever was last put through it, and two pages
     * render one of these — the delete confirmation and the modal on the edit page.
     */
    public function deleteForm(): DeleteUserForm
    {
        return new DeleteUserForm($this->adapter);
    }

    /**
     * The row behind a `{user_id}` route parameter, or null when there is none.
     *
     * `'0'` is rejected rather than looked up: a route constraint of `[0-9]{1,5}` matches
     * `00000`, and every caller's next line is `(int) $user['userId']`.
     *
     * @return array<string, mixed>|null
     */
    public function user(Request $request): ?array
    {
        /** @var mixed $raw */
        $raw = $request->attributes->get('user_id');
        $raw = is_string($raw) || is_int($raw) ? (string) $raw : '';
        if (! ctype_digit($raw) || 0 === (int) $raw) {
            return null;
        }

        /** @var mixed $user */
        $user = $this->users->getUser((int) $raw);

        return is_array($user) ? $user : null;
    }

    /**
     * Every person, keyed by id, for the index page's Person column — or null when this
     * host configures no provider.
     *
     * `getPersons()`, not `getPersonValueOptions()`: the column renders a person record and
     * needs the row, not a label. The two are different methods on the same interface and
     * the edit form uses the other one.
     *
     * The null is a real answer rather than an error, and the index template guards on it:
     * a host with no person concept renders the column empty for every row. Note what this
     * *cannot* do any more — the version this was ported from resolved a service id out of
     * config and threw when it named something of the wrong type. A host injecting a typed
     * provider makes that unrepresentable, so the check went where the resolution went.
     *
     * @return array<int|string, mixed>|null
     */
    public function persons(): ?array
    {
        if (null === $this->persons) {
            return null;
        }

        /** @var mixed $persons */
        $persons = $this->persons->getPersons();

        return is_array($persons) ? $persons : null;
    }

    public function apiTokens(): ApiTokenService
    {
        return $this->apiTokens;
    }

    /**
     * The one-shot slot a freshly minted JWT travels in, between the POST that issues it
     * and the GET that displays it. Read and cleared in one act.
     *
     * **Its own namespace, deliberately not the sign-in flow's**, so that nothing walking
     * that container looking for a post-login redirect ever sees a credential.
     *
     * A session slot rather than a message, and the reason is an incident rather than a
     * preference: the messengers translate the finished message at render time, and a
     * translator miss is exactly what files a phrase — so a version that appended the JWT
     * to the message put four real tokens into a table any translator account can read, and
     * copied them again into the English translation and the exported catalog on disk. On
     * the one screen whose own copy says the token is not stored.
     */
    /** @return array<string, mixed>|null */
    public function takeIssuedToken(): ?array
    {
        /** @var mixed $stored */
        $stored = $this->session->get(self::TOKEN_NAMESPACE, self::TOKEN_SLOT);
        $this->session->remove(self::TOKEN_NAMESPACE, self::TOKEN_SLOT);

        return is_array($stored) ? $stored : null;
    }

    /** @param array<string, mixed> $token */
    public function rememberIssuedToken(array $token): void
    {
        $this->session->set(self::TOKEN_NAMESPACE, self::TOKEN_SLOT, $token);
    }

    /** The administrator performing this request, for the provenance columns. */
    public function actingUserId(): ?int
    {
        return $this->actingUser?->getActingUserId();
    }

    /**
     * Survives a redirect; read by the next page rendered in this session.
     *
     * A message belongs here exactly when the response carrying it is a redirect — see
     * {@see now()}, and note that this surface has shipped the wrong one of the two: a
     * failed validation reported with a flash and then re-rendered puts "please review" on
     * whatever page the administrator opens next, while the field-level errors sit
     * unexplained on the form in front of them.
     *
     * @param string|TranslatableMessage $message
     */
    public function flash(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages->flash($severity, $message);
    }

    /**
     * Rendered by the response being returned now.
     *
     * @param string|TranslatableMessage $message a `TranslatableMessage` is how data
     *        reaches a message without becoming part of the phrase key
     */
    public function now(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages->now($severity, $message);
    }

    /**
     * Record a write that did not happen, and say so in one sentence.
     *
     * These actions used to answer every failure — a validator complaining, a NOT NULL
     * column rejecting a checkbox that posted nothing, a table that had not been migrated —
     * with the same "Error in form submission, please review." and nothing in the log.
     * Reviewing the form could not help, because the form was fine; the only way to find
     * out what happened was to put a debugger in the catch block. So the log gets the
     * exception, the administrator gets told plainly that nothing was saved, and the two are
     * distinguishable from a form they really can fix.
     *
     * The message deliberately carries no exception text: an administrator cannot act on an
     * SQLSTATE, and a stack trace on a page is how internals leak.
     *
     * @param string $what gerund phrase, e.g. 'creating a user'
     * @param Throwable|null $e null when the call reported failure by return value
     * @param array<string, mixed> $context extra fields for the log line
     * @return string the message to show
     */
    public function writeFailure(string $what, ?Throwable $e = null, array $context = []): string
    {
        if (null !== $this->logger) {
            $this->logger->error(sprintf('JUser: Failed %s.', $what), $context + [
                'exceptionClass' => null === $e ? null : get_class($e),
                'exception'      => null === $e ? null : $e->getMessage(),
                'trace'          => null === $e ? null : $e->getTraceAsString(),
            ]);
        }

        return sprintf('%s failed — nothing was saved. The error has been logged.', ucfirst($what));
    }

    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
