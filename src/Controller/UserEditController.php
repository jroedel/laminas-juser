<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Form\EditUserForm;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Page\UserAdmin;
use JUser\Routing\Routes;
use JUser\Twig\JUserExtension;
use SionModel\Form\Element\Select;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_scalar;

/**
 * GET|POST /users/{user_id}/edit — one account's own form.
 *
 * Four details separate this from a generic entity-edit action, and each is here rather
 * than approximated:
 *
 * 1. **`prepareForEdit()`** — `setValidationGroup(array_keys($this->getElements()))`, i.e.
 *    validate exactly the elements the form declares and nothing else. Without it a POST
 *    carrying an extra key is refused rather than ignored.
 * 2. **The posted `userId` is checked against the URL.** A mismatch is not a validation
 *    error: it redirects to the index. The hidden field is the form's own, so the only way
 *    to trip this is to edit it, and the check is what stops an edit of account A writing to
 *    account B.
 * 3. **`personId` is dropped when it was not posted at all.** `isset($posted['personId'])`
 *    is read off the *raw* POST, before validation, and the key is unset from the validated
 *    data when it was absent. That matters because the select renders only when the person
 *    provider offered options — so on a host with no provider the field never appears, and
 *    without this the form's own `ToNull` filter would write NULL over a person reference the
 *    form never showed anyone.
 * 4. **A delete form travels with the page**, for the modal the template renders. Built per
 *    call: a form carries the data and the messages of whatever was last validated through
 *    it.
 *
 * ## Which messenger, and the defect that taught the difference
 *
 * A failed validation used to be reported with a **flash** and the form then re-rendered. A
 * flash is read by the *next* page, so the administrator saw a clean form with no
 * explanation and then found "Error in form submission, please review." decorating whatever
 * they opened next — while the field-level errors, which do render, sat there unexplained.
 *
 * So: the two re-rendering branches use `now()` and the two that redirect flash. That is the
 * whole rule, and it is worth stating as one — a message belongs in the flash bag exactly
 * when the response carrying it is a redirect.
 */
final class UserEditController
{
    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly UrlBuilderInterface $urls
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        $user = $this->admin->user($request);
        if (null === $user) {
            $this->admin->flash(Severity::Error, 'User not found.');

            return $this->toIndex();
        }
        $id = (int) $user['userId'];

        /** @var EditUserForm $form */
        $form = $this->admin->form(EditUserForm::class);
        $form->prepareForEdit();
        //setData twice on the GET path, as this action has always done: once here so the
        //form is populated before the POST branch can decide not to run, and again below.
        //Harmless, and kept so the two read the same.
        $form->setData($user);

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();

            //Read off the raw POST, before validation — see the class docblock.
            $postedPersonId = isset($posted['personId']);

            /** @var mixed $postedUserId */
            $postedUserId = $posted['userId'] ?? null;
            //Compared as strings rather than loosely, which is the same answer for every
            //value a `[0-9]{1,5}` route constraint can produce and does not treat a stray
            //non-numeric body as a match the way `0 == 'abc'` once would have.
            if (! is_scalar($postedUserId) || (string) $postedUserId !== (string) $id) {
                $this->admin->flash(Severity::Error, 'Error in form submission, please try again later.');

                return $this->toIndex();
            }

            $form->setData($posted);
            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                if (! $postedPersonId) {
                    unset($data['personId']);
                }

                $this->admin->logger()?->info('Updating user', ['userId' => $id, 'data' => $data]);

                if ($this->admin->table()->updateEntity('user', $id, $data)) {
                    $this->admin->flash(Severity::Success, 'User successfully updated.');

                    return $this->toIndex();
                }

                //now(), not flash(): this branch re-renders the form below
                $this->admin->now(Severity::Error, 'Error in form submission, please review.');
            } else {
                $this->admin->now(Severity::Error, 'Error in form submission, please review.');
            }
        }

        $deleteForm = $this->admin->deleteForm();
        $deleteForm->setData(['userId' => $id]);

        return new Response($this->twig->render(JUserExtension::template('user-edit'), [
            'page_title'  => 'Edit User',
            'user_id'     => $id,
            'user'        => $user,
            'form'        => $form,
            'delete_form' => $deleteForm,
            //The modal's form *does* carry an action, unlike the edit form itself: it posts
            //to a different route.
            'delete_action' => $this->urls->path(Routes::USER_DELETE, ['user_id' => $id]),
            //Decided here so the template reads as markup; the answer is a property of the
            //host's person provider, not of this account.
            'show_person'   => $this->hasPersonOptions($form),
        ]));
    }

    /**
     * Whether the `personId` select has any options at all.
     *
     * Narrowed to `Select` rather than asserted, because `Form::get()` answers an
     * `ElementInterface` and only a `Select` has value options. A `personId` that is not one
     * would be a change to `EditUserForm`, and answering "no options" is the right reading of
     * that: the field being asked about is not there.
     */
    private function hasPersonOptions(EditUserForm $form): bool
    {
        $element = $form->get('personId');

        return $element instanceof Select && [] !== $element->getValueOptions();
    }

    private function toIndex(): RedirectResponse
    {
        return new RedirectResponse($this->urls->path(Routes::USERS), Response::HTTP_FOUND);
    }
}
