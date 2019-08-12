<?php

namespace JUser\Controller;

use JUser\Form\EditUserForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use JUser\Model\UserTable;
use JUser\Form\ChangeOtherPasswordForm;
use JUser\Form\DeleteUserForm;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Zend\Crypt\Password\Bcrypt;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Form\CreateRoleForm;
use JUser\Service\Mailer;
use JUser\Model\User;
use Zend\Log\LoggerInterface;

/**
 *
 * @author Jeff Ro <jeff.roedel@gmail.com>
 * @todo   fix activation
 * @todo   email admins to alert new user request
 */
class UsersController extends AbstractActionController
{
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_EXPIRED = 'expired';

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
        if (!array_key_exists($identifier, $this->services)) {
            throw new \Exception("No service `$identifier` found.");
        }
        return $this->services[$identifier];
    }

    public function thanksAction()
    {
    }

    public function changePasswordAction()
    {
        $id = (int)$this->params('user_id');
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        /** @var UserTable $table */
        $table = $this->getService(UserTable::class);
        $zfcOptions = $this->getService('zfcuser_module_options');
        $form = new ChangeOtherPasswordForm($zfcOptions);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            if ($data['userId'] != $id) {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission.');
                return $this->redirect()->toRoute('juser');
            }
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                $bcrypt = new Bcrypt();
                $bcrypt->setCost($zfcOptions->getPasswordCost());
                $pass = $bcrypt->create($data['newCredential']);
                $table->updateUserPassword($id, $pass);
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                    ->addMessage('User password updated successfully.');
                return $this->redirect()->toRoute('juser');
            } else {
                $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Please review the form and resubmit.');
            }
        } else {
            $userIdData = ['userId' => $id];
            $form->setData($userIdData);
        }
        $user = $table->getUser($id);

        return [
            'userId' => $id,
            'user' => $user,
            'form' => $form,
        ];
    }

    public function verifyEmailAction()
    {
        $token = $this->params()->fromQuery('token');
        if (!isset($token)) {
            $this->redirect()->toRoute('welcome');
        }

        /** @var UserTable $table */
        $table = $this->getService(UserTable::class);
        $logger = $table->getLogger();
        if (isset($logger)) {
            $logger->debug("JUser: receiving a request to verify user", ['verificationToken' => $token]);
        }
        $user = $table->getUserFromToken($token);
        if (!isset($user)) {
            if (isset($logger)) {
                $logger->alert("JUser: we were unable to find the user based on their verification token.", ['verificationToken' => $token]);
            }
            //@todo add a requestEmailVerificationAction(), redirect users to this
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Unable to verify email address.');
            return $this->redirect()->toRoute('welcome'); //@todo make this configurable
        }

        //expired or validated
        $status = null;
        $now = new \DateTime(null, new \DateTimeZone('UTC'));
        if (!isset($user['verificationExpiration']) || $now > $user['verificationExpiration']) {
            $status = self::VERIFICATION_EXPIRED;
            $user = self::setNewVerificationToken($user);
            $table->updateEntity('user', $user['userId'], $user);
            if (isset($logger)) {
                $logger->alert(
                    "JUser: The user's verification token was expired, we'll send them a new one.", 
                    ['email' => $user['email']]
                    );
            }
            /** @var Mailer $mailer */
            $mailer = $this->getService(Mailer::class);
            $mailer->sendVerificationEmail($user);
        } else {
            $status = self::VERIFICATION_VERIFIED;
            if (isset($logger)) {
                $logger->info(
                    "JUser: The user was successfully verified.",
                    ['email' => $user['email']]
                    );
            }
            $user['active'] = true;
            $user['emailVerified'] = true;
            //update status
            $table->updateEntity('user', $user['userId'], $user);

            //@todo allow spontaeneous login
        }
        return new ViewModel([
            'user' => $user,
            'status' => $status,
        ]);
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
                if (!$provider instanceof PersonValueOptionsProviderInterface) {
                    throw new \InvalidArgumentException(
                        '`person_provider` specified in the JUser config does'
                        .' not implement the PersonValueOptionsProviderInterface.'
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
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        $user = $table->getUser($id);
        if (!$user) {
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
                if (!$isPersonIdSet) {
                    unset($data['personId']);
                }
                if (isset($this->logger)) {
                    $this->logger->info("Updating user", ['userId' => $id, 'data' => $data]);
                }
                if ($table->updateEntity('user', $id, $data)) {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('User successfully updated.');
//                     $this->redirect()->toUrl($this->url()->fromRoute('juser'));
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
        $changePasswordForm = new ChangeOtherPasswordForm($this->getService('zfcuser_module_options'));
        $changePasswordForm->setData($userIdData);
        $deleteUserForm = new DeleteUserForm();
        $deleteUserForm->setData($userIdData);

        return new ViewModel([
            'userId' => $id,
            'user' => $user,
            'form' => $form,
            'changePasswordForm' => $changePasswordForm,
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
                //the validators should've already confirmed the passwordVerify field
                //@todo move this to CreateUserForm
                if (isset($data['password']) && $data['password']) {
                    $data['password'] = $this->hashPassword($data['password']);
                }
                try {
                    if (!($table->createEntity('user', $data))) {
                        $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                            ->addMessage('Error in form submission, please review.');
                    } else {
                        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                            ->addMessage('User successfully created.');
                        $this->redirect()->toRoute('juser');
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

    protected function hashPassword($password)
    {
        $zfcOptions = $this->getService('zfcuser_module_options');
        $bcrypt = new Bcrypt();
        $bcrypt->setCost($zfcOptions->getPasswordCost());
        $pass = $bcrypt->create($password);
        return $pass;
    }

    protected function generatePassword()
    {
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
                    $this->redirect()->toRoute('juser');
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
        if (!$id) {
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
                        ->addMessage('Database error deleting user. '.$result);
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

    protected static function setNewVerificationToken($user)
    {
        $user['verificationToken'] = User::generateVerificationToken();
        $dt = new \DateTime(null, new \DateTimeZone('UTC'));
        $dt->add(new \DateInterval('P1D'));
        $user['verificationExpiration'] = $dt;
        return $user;
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
