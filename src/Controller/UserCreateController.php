<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Form\CreateRoleForm;
use JUser\Form\EditUserForm;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Page\UserAdmin;
use JUser\Routing\Routes;
use JUser\Twig\JUserExtension;
use Laminas\Form\FormInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function array_key_exists;
use function array_keys;
use function is_string;

/**
 * The two "new record" forms of the user-administration surface.
 *
 * | route | form | writes |
 * |---|---|---|
 * | `juser/create` | `EditUserForm` | `createEntity('user', …)` |
 * | `juser/create-role` | `CreateRoleForm` | `createEntity('user-role', …)` |
 *
 * One controller, told which record it is making by a route default — see {@see KIND} and
 * the route fragment. Both redirect to the index unconditionally on success.
 *
 * ## Two differences between the two, both kept
 *
 * 1. **The user branch checks `createEntity()`'s return value; the role branch does not.**
 *    It answers the new id, or something falsy when the write reported failure without
 *    throwing. Left as it is: the honest fix is to make `createEntity()` throw, not to add a
 *    check on one of two call sites and leave the surface inconsistent in a new way.
 * 2. **Only the user branch pre-selects anything.** A GET of `juser/create` sets `rolesList`
 *    to the default roles — what registration would have granted — **and ticks Active**.
 *
 * ## Ticking Active is not cosmetic
 *
 * The element declares `'value' => 0`, so without this the box renders unchecked and an
 * administrator who does not notice creates an account at `state = 0`. That is a permanent
 * silent lockout: `state = 0` means "may not sign in", so the account is refused a magic
 * link, refused a link already in flight, and told nothing either time — the page still says
 * "check your email", because it must not reveal which accounts are disabled. Nothing would
 * ever surface it. Unticking the box is still available and is now an explicit choice:
 * create the account, do not let them in yet.
 */
final class UserCreateController
{
    /** Route default naming which of the two records this route creates. */
    public const KIND = '_juser_create';

    public const USER = 'user';
    public const ROLE = 'user-role';

    /** kind => [form service, template, heading, gerund, id field for the log line] */
    private const PAGES = [
        self::USER => [EditUserForm::class, 'user-create', 'Create new user', 'creating a user', 'username'],
        self::ROLE => [CreateRoleForm::class, 'role-create', 'Create new role', 'creating a role', 'roleId'],
    ];

    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly UrlBuilderInterface $urls
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        $kind = $request->attributes->get(self::KIND);
        if (! is_string($kind) || ! array_key_exists($kind, self::PAGES)) {
            throw new RuntimeException('A juser create route declared no known record kind.');
        }
        [$formId, $template, $heading, $gerund, $idField] = self::PAGES[$kind];

        $form = $this->form($formId, $kind);

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $done = $this->write($kind, $data, $gerund, $idField);
                if (null !== $done) {
                    return $done;
                }
            } else {
                //now(), not flash(): the form is re-rendered on *this* response
                $this->admin->now(Severity::Error, 'Error in form submission, please review.');
            }
        } elseif (self::USER === $kind) {
            $form->get('rolesList')->setValue(array_keys($this->admin->table()->getDefaultRoles()));
            //see the class docblock — without this an administrator can create an account
            //that is silently unable to ever sign in
            $form->get('active')->setValue(1);
        }

        //**No `action` attribute**, which is why the templates call `form_open(form, '')`.
        //Neither view sets one, so the browser posts to the current URL — and `action=""` is
        //not the same thing to a browser resolving a relative reference, which is what an
        //empty string passed through to the attribute would become.
        return new Response($this->twig->render(JUserExtension::template($template), [
            'page_title' => $heading,
            'form'       => $form,
        ]));
    }

    /**
     * The write. A RedirectResponse on success, null to re-render the form with a message.
     *
     * @param array<string, mixed> $data
     */
    private function write(string $kind, array $data, string $gerund, string $idField): ?RedirectResponse
    {
        $table = $this->admin->table();

        try {
            $created = $table->createEntity($kind, $data);
        } catch (Throwable $e) {
            $this->admin->now(Severity::Error, $this->admin->writeFailure($gerund, $e, [
                $idField => $data[$idField] ?? null,
            ]));

            return null;
        }

        //Only the user branch reads the return value — see the class docblock.
        if (self::USER === $kind && ! $created) {
            $this->admin->now(Severity::Error, $this->admin->writeFailure($gerund, null, [
                $idField => $data[$idField] ?? null,
            ]));

            return null;
        }

        $this->admin->flash(
            Severity::Success,
            self::USER === $kind ? 'User successfully created.' : 'Role successfully created.'
        );

        return new RedirectResponse($this->urls->path(Routes::USERS), Response::HTTP_FOUND);
    }

    /**
     * The form, primed the way this surface has always primed it.
     *
     * `setValidatorsForCreate()` then `setName('create_user')` for the user form, in that
     * order and both on the instance the locator hands back. The rename is not cosmetic: it
     * is what puts `name="create_user" id="create_user"` on the `<form>` instead of
     * `user_edit`, which is the only thing distinguishing the create form's markup from the
     * edit form's.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function form(string $formId, string $kind): FormInterface
    {
        /** @var FormInterface<array<string, mixed>> $form */
        $form = $this->admin->form($formId);

        if (self::USER === $kind) {
            /** @var EditUserForm $form */
            $form->setValidatorsForCreate();
            $form->setName('create_user');
        }

        return $form;
    }
}
