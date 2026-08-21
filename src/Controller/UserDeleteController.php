<?php

declare(strict_types=1);

namespace JUser\Controller;

use JUser\Form\DeleteUserForm;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Page\UserAdmin;
use JUser\Routing\Routes;
use JUser\Twig\JUserExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_array;
use function is_scalar;
use function is_string;

/**
 * GET|POST /users/{user_id}/delete — delete one account.
 *
 * The GET renders a confirmation; the POST deletes. Both are reached: the edit form carries
 * a modal that posts here, and the GET page is what a typed URL answers.
 *
 * `UserTable::deleteUser()` rather than a generic entity delete, because the row has role
 * links hanging off it and the table's own method is what removes them.
 *
 * ## Exactly one of three outcomes is reported
 *
 * Worth stating because it was not always true: an ancestor of this action ran the error
 * branch and then appended 'User deleted.' unconditionally, so a failed delete and a refused
 * CSRF token both ended on a green success message contradicting the red one above it. The
 * three branches — refused, failed, done — are mutually exclusive and each redirects to the
 * index.
 *
 * A refused token and an unknown account report the same 'User not found.', which is
 * misleading and is kept: it is the message the surface has always given.
 *
 * ## The confirmation names the account, and its predecessor did not
 *
 * The laminas view filled its `%s` from `$this->user->username` — **property access on an
 * array**, because `getUser()` answers one. So on every render since it was written the page
 * asked whether to permanently delete `''`, with a PHP warning where the name should be;
 * invisible wherever `display_errors` is off, which is everywhere that matters.
 *
 * Not reproduced, and it is the one place in this port where reproducing would have been
 * wrong: a destructive confirmation that names nobody is a safety defect, not a cosmetic
 * one, and it is the entire content of the page. The same reasoning retires the other half
 * of it — an id that names no account redirects to the index rather than rendering a
 * confirmation for an account that does not exist.
 */
final class UserDeleteController
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

        $form = $this->admin->deleteForm();

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if (! $form->isValid() || ! $this->confirms($form, $id)) {
                $this->admin->flash(Severity::Error, 'User not found.');
            } else {
                $result = $this->admin->table()->deleteUser($id);
                if (1 !== $result) {
                    $this->admin->flash(
                        Severity::Error,
                        $this->admin->writeFailure('deleting a user', null, [
                            'userId' => $id,
                            'result' => $result,
                        ])
                    );
                } else {
                    $this->admin->flash(Severity::Success, 'User deleted.');
                }
            }

            return $this->toIndex();
        }

        $form->setData(['userId' => $id]);

        return new Response($this->twig->render(JUserExtension::template('user-delete'), [
            //`<h1>Warning...</h1>` is a literal in the view with no page title at all, so
            //the browser tab shows the site name alone. Reproduced with an **empty**
            //page_title rather than by omitting it: a layout that reads `page_title` with no
            //`is defined` guard under strict_variables turns an omission into a fatal, and
            //such a fatal arrives as a 200 with an empty body.
            'page_title' => '',
            'user_id'    => $id,
            'username'   => $this->username($user),
            'form'       => $form,
            'self_url'   => $this->urls->path(Routes::USER_DELETE, ['user_id' => $id]),
        ]));
    }

    /**
     * Whether the validated form names the account in the URL.
     *
     * The hidden field is the form's own, so tripping this means editing it, and what it
     * stops is a confirmation for account A deleting account B.
     */
    private function confirms(DeleteUserForm $form, int $id): bool
    {
        /** @var mixed $data */
        $data = $form->getData();
        /** @var mixed $confirmed */
        $confirmed = is_array($data) ? ($data['userId'] ?? null) : null;

        return is_scalar($confirmed) && (string) $confirmed === (string) $id;
    }

    /**
     * The name the confirmation shows — see the class docblock on why this is a key read
     * and not a property read.
     *
     * `username` is nullable in the table, which is the case the fallback is for: an account
     * created from an email whose local part filtered down to nothing gets one from
     * `makeUniqueUsername()`, but nothing enforces it at the column. Showing the address
     * beats showing empty quotes on a page asking for a deletion.
     *
     * @param array<string, mixed> $user
     */
    private function username(array $user): string
    {
        /** @var mixed $username */
        $username = $user['username'] ?? null;
        if (is_string($username) && '' !== $username) {
            return $username;
        }

        /** @var mixed $email */
        $email = $user['email'] ?? null;

        return is_string($email) ? $email : '';
    }

    private function toIndex(): RedirectResponse
    {
        return new RedirectResponse($this->urls->path(Routes::USERS), Response::HTTP_FOUND);
    }
}
