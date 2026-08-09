<?php

namespace JUser\Controller;

use JUser\Form\EditUserForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Form\DeleteUserForm;
use JUser\Form\IssueApiTokenForm;
use JUser\Form\RevokeApiTokenForm;
use JUser\Service\ApiTokenService;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Form\CreateRoleForm;
use Psr\Log\LoggerInterface;
use SionModel\Service\ActingUserProviderInterface;

/**
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 * @todo   email admins to alert new user request
 */
class UsersController extends AbstractActionController
{
    protected $userTable;

    protected $services = [];

    protected $logger;

    /**
     *
     * @return UserTable
     */
    public function setUserTable(UserTable $userTable)
    {
        $this->userTable = $userTable;
    }

    public function setServices($services)
    {
        $this->services = $services;
    }

    public function hasService($identifier)
    {
        return array_key_exists($identifier, $this->services);
    }

    public function getService($identifier)
    {
        if (! array_key_exists($identifier, $this->services)) {
            throw new \Exception("No service `$identifier` found.");
        }
        return $this->services[$identifier];
    }

    public function thanksAction()
    {
    }

    /**
     * Legacy email-verification endpoint.
     *
     * Email verification and sign-in are now the same act: redeeming a single-use
     * token. Everything is handled by JUser\Controller\LoginController::verifyAction,
     * so this route only forwards the token there and stays alive for old links.
     */
    public function verifyEmailAction()
    {
        $token = $this->params()->fromQuery('token');
        if (! isset($token) || '' === $token) {
            return $this->redirect()->toRoute('welcome');
        }
        return $this->redirect()->toRoute('zfcuser/verify', [], ['query' => ['token' => $token]]);
    }

    public function indexAction()
    {
        $persons = null;

        $config = $this->getService('JUser\Config');
        if (key_exists('person_provider', $config)) {
            $personProvider = $config['person_provider'];
            if ($this->hasService($personProvider)) {
                /** @var PersonValueOptionsProviderInterface $provider **/
                $provider = $this->getService($personProvider);
                if (! $provider instanceof PersonValueOptionsProviderInterface) {
                    throw new \InvalidArgumentException(
                        '`person_provider` specified in the JUser config does'
                        . ' not implement the PersonValueOptionsProviderInterface.'
                    );
                }
                $persons = $provider->getPersons();
            }
        }
        $users = $this->userTable->getUsers();
        return new ViewModel([
            'users' => $users,
            'persons' => $persons
        ]);
    }

    public function editAction()
    {
        /** @var UserTable $table **/
        $table = $this->userTable;
        $id = (int) $this->params()->fromRoute('user_id');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        $user = $table->getUser($id);
        if (! $user) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }

        /** @var EditUserForm $form */
        $form = $this->getService(EditUserForm::class);
        $form->prepareForEdit();
        $form->setData($user);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            if ($data['userId'] != $id) { // make sure the user is trying to update the right user
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please try again later.');
                return $this->redirect()->toRoute('juser');
            }
            $isPersonIdSet = isset($data['personId']);
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (! $isPersonIdSet) {
                    unset($data['personId']);
                }
                if (isset($this->logger)) {
                    $this->logger->info("Updating user", ['userId' => $id, 'data' => $data]);
                }
                if ($table->updateEntity('user', $id, $data)) {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('User successfully updated.');
                    return $this->redirect()->toUrl($this->url()->fromRoute('juser'));
                } else {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Error in form submission, please review.');
                }
            } else {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        }
        $userIdData = ['userId' => $id];
        $deleteUserForm = new DeleteUserForm();
        $deleteUserForm->setData($userIdData);

        return new ViewModel([
            'userId' => $id,
            'user' => $user,
            'form' => $form,
            'deleteUserForm' => $deleteUserForm,
        ]);
    }

    public function createAction()
    {
        /** @var UserTable $table **/
        $table = $this->userTable;

        /** @var EditUserForm $form */
        $form = $this->getService(EditUserForm::class);

        //@todo find a way to get this out of here
        $form->setValidatorsForCreate();
        $form->setName('create_user');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                //passwords are gone; the column is NOT NULL so it gets an empty string
                $data['password'] = '';
                try {
                    if (! ($table->createEntity('user', $data))) {
                        $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                            ->addMessage('Error in form submission, please review.');
                    } else {
                        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                            ->addMessage('User successfully created.');
                        return $this->redirect()->toRoute('juser');
                    }
                } catch (\Exception $e) {
                    $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Error in form submission, please review.');
                }
            } else {
                $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        } else {
            $rolesList = $form->get('rolesList');
            $defaultRoles = $table->getDefaultRoles();
            $rolesList->setValue(array_keys($defaultRoles));
        }
        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function createRoleAction()
    {
        /** @var UserTable $table **/
        $table = $this->userTable;

        /** @var EditUserForm $form */
        $form = $this->getService(CreateRoleForm::class);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $table->createEntity('user-role', $data);
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('Role successfully created.');
                    return $this->redirect()->toRoute('juser');
                } catch (\Exception $e) {
                    $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Error in form submission, please review.');
                }
            } else {
                $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        }
        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function deleteAction()
    {
        $id = (int)$this->params('user_id');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }

        $table = $this->userTable;
        $form = new DeleteUserForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid() && $form->getData()['userId'] == $id) {
                if (1 != ($result = $table->deleteUser($id))) {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Database error deleting user. ' . $result);
                }
            } else {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('User not found.');
            }
            // Redirect to list of users
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                ->addMessage('User deleted.');
            return $this->redirect()->toRoute('juser');
        } else {
            $userIdData = ['userId' => $id];
            $form->setData($userIdData);
        }
        $user = $table->getUser($id);

        return new ViewModel([
            'userId' => $id,
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * List an account's API tokens, and issue a new one.
     *
     * The freshly minted JWT is shown **once**, in the flash message that
     * follows the redirect, and is never stored anywhere we could show it again:
     * only the jti is recorded. An admin who loses it issues another and revokes
     * this one, which costs nothing — that is the point of making issuance cheap.
     */
    public function apiTokensAction()
    {
        $id = (int) $this->params('user_id');
        $user = $id ? $this->userTable->getUser($id) : null;
        if (! is_array($user)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }

        /** @var ApiTokenService $tokenService */
        $tokenService = $this->getService(ApiTokenService::class);
        $mayIssue = $tokenService->mayIssueForUser($id);

        $form = new IssueApiTokenForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            //Re-checked on POST, not merely hidden in the view. The GET decided
            //whether to *draw* a button; this decides whether to mint a
            //credential, and a hand-crafted POST never saw the view at all.
            if (! $mayIssue) {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('This account may not be issued an API token.');
                return $this->redirect()->toRoute('juser/user/api-tokens', ['user_id' => $id]);
            }

            $form->setData($request->getPost());
            if ($form->isValid()) {
                try {
                    $issued = $tokenService->issue(
                        new User($user),
                        $form->getData()['label'] ?? null,
                        $this->actingUserId()
                    );
                    //NAMESPACE_SUCCESS and not the log: the token is the one
                    //thing that must never be written down by us.
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage(
                            'Token issued. Copy it now — it is not shown again and we do not store it: '
                            . $issued['jwt']
                        );
                } catch (\Exception $e) {
                    if (isset($this->logger)) {
                        $this->logger->error("JUser: Failed to issue an API token.", [
                            'userId' => $id,
                            'exception' => $e,
                        ]);
                    }
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Could not issue a token: ' . $e->getMessage());
                }
            } else {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }

            return $this->redirect()->toRoute('juser/user/api-tokens', ['user_id' => $id]);
        }

        return new ViewModel([
            'userId'        => $id,
            'user'          => $user,
            'tokens'        => $tokenService->getTokensForUser($id),
            'form'          => $form,
            'revokeForm'    => new RevokeApiTokenForm(),
            'mayIssue'      => $mayIssue,
            'issuableRoles' => $tokenService->getIssuableRoles(),
            'lifetimeDays'  => $tokenService->getLifetimeDays(),
        ]);
    }

    /**
     * Revoke one token. POST only.
     */
    public function revokeApiTokenAction()
    {
        $id = (int) $this->params('user_id');
        $tokenId = (int) $this->params('token_id');
        $request = $this->getRequest();

        if (! $request->isPost()) {
            return $this->redirect()->toRoute('juser/user/api-tokens', ['user_id' => $id]);
        }

        $form = new RevokeApiTokenForm();
        $form->setData($request->getPost());
        if (! $form->isValid()) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('That request expired, please try again.');
            return $this->redirect()->toRoute('juser/user/api-tokens', ['user_id' => $id]);
        }

        /** @var ApiTokenService $tokenService */
        $tokenService = $this->getService(ApiTokenService::class);

        //Scoped by user id as well as token id, so a token id belonging to
        //another account cannot be revoked from this account's page. Also why the
        //"already revoked" case is a plain message rather than an error: the
        //honest reading of a second submit is a double-click.
        if ($tokenService->revoke($tokenId, $id, $this->actingUserId())) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                ->addMessage('Token revoked. It stops working on its next request.');
        } else {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_INFO)
                ->addMessage('That token was already revoked, or does not belong to this account.');
        }

        return $this->redirect()->toRoute('juser/user/api-tokens', ['user_id' => $id]);
    }

    /**
     * The administrator performing this request, for the provenance columns.
     *
     * Resolved at call time through SionModel's provider rather than captured in
     * the factory — see ActingUserProviderInterface on why identity must never be
     * read while the container is still building.
     *
     * @return int|null
     */
    protected function actingUserId()
    {
        if (! $this->hasService(ActingUserProviderInterface::class)) {
            return null;
        }

        return $this->getService(ActingUserProviderInterface::class)->getActingUserId();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger()
    {
        return $this->logger;
    }
}
