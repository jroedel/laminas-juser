<?php

namespace JUser\Controller;

use JUser\Form\EditUserForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use JUser\Model\UserTable;
use JUser\Form\DeleteUserForm;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Form\CreateRoleForm;
use Psr\Log\LoggerInterface;

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
