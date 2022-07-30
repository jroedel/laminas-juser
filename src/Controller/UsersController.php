<?php

declare(strict_types=1);

namespace JUser\Controller;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use JTranslate\Controller\Plugin\NowMessenger;
use JUser\Form\ChangeOtherPasswordForm;
use JUser\Form\CreateRoleForm;
use JUser\Form\DeleteUserForm;
use JUser\Form\EditUserForm;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Service\Mailer;
use Laminas\Crypt\Password\Bcrypt;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use LmcUser\Options\ModuleOptions;
use SionModel\Person\PersonProviderInterface;
use Webmozart\Assert\Assert;

use function array_keys;
use function implode;

class UsersController extends AbstractActionController
{
    public const VERIFICATION_VERIFIED                  = 'verified';
    public const VERIFICATION_EXPIRED                   = 'expired';
    public const VERIFICATION_TOKEN_EXPIRATION_INTERVAL = 'P1D';

    public function __construct(
        private UserTable $userTable,
        private LoggerInterface $logger,
        private ModuleOptions $lmcModuleOptions,
        private Mailer $mailer,
        private EditUserForm $editUserForm,
        private CreateRoleForm $createRoleForm,
        private ChangeOtherPasswordForm $changeOtherPasswordForm,
        private ?PersonProviderInterface $personProvider,
    ) {
    }

    public function thanksAction(): ModelInterface|ResponseInterface
    {
        return new ViewModel();
    }

    public function changePasswordAction(): ModelInterface|ResponseInterface
    {
        $id = (int) $this->params('user_id');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isInstanceOf($nowMessenger, NowMessenger::class);
        $table   = $this->userTable;
        $form    = $this->changeOtherPasswordForm;
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                $table->updateUserPassword($id, $data['newCredential']);
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                    ->addMessage('User password updated successfully.');
                return $this->redirect()->toRoute('juser');
            } else {
                $messages = $form->getMessages();
                $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
                $nowMessenger->addMessage(
                    'Error in form submission, please review: ' . implode(', ', array_keys($messages))
                );
            }
        }
        $user = $table->getUser($id);

        return new ViewModel([
            'user' => $user,
            'form' => $form,
        ]);
    }

    public function verifyEmailAction(): ModelInterface|ResponseInterface
    {
        $token = $this->params()->fromQuery('token');
        if (! isset($token)) {
            $this->redirect()->toRoute('welcome');
        }

        $table  = $this->userTable;
        $logger = $this->logger;
        $logger->info("JUser: receiving a request to verify user", ['verificationToken' => $token]);

        $user = $table->getUserFromToken($token);
        if (! isset($user)) {
            $logger->alert(
                "JUser: we were unable to find the user based on their verification token.",
                ['verificationToken' => $token]
            );

            //@todo add a requestEmailVerificationAction(), redirect users to this
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Unable to verify email address.');
            return $this->redirect()->toRoute('welcome'); //@todo make this configurable
        }

        //expired or validated
        $now = new DateTime('now', new DateTimeZone('UTC'));
        if (! isset($user['verificationExpiration']) || $now > $user['verificationExpiration']) {
            $status = self::VERIFICATION_EXPIRED;
            $user   = self::setNewVerificationToken($user);
            $table->updateEntity('user', $user['userId'], $user);

            $logger->alert(
                "JUser: The user's verification token was expired, we'll send them a new one.",
                ['email' => $user['email']]
            );

            $mailer = $this->mailer;
            $mailer->sendVerificationEmail($user);
        } else {
            $status = self::VERIFICATION_VERIFIED;

            $logger->info(
                "JUser: The user was successfully verified.",
                ['email' => $user['email']]
            );

            $user['active']        = 1;
            $user['emailVerified'] = true;
            //update status
            $table->updateEntity('user', $user['userId'], $user);

            //@todo allow spontaneous login
        }
        return new ViewModel([
            'user'   => $user,
            'status' => $status,
        ]);
    }

    public function indexAction(): ModelInterface|ResponseInterface
    {
        $persons = $this->personProvider?->getPersons();
        $users   = $this->userTable->getUsers();
        return new ViewModel([
            'users'   => $users,
            'persons' => $persons,
        ]);
    }

    public function editAction(): ModelInterface|ResponseInterface
    {
        $table = $this->userTable;
        $id    = (int) $this->params()->fromRoute('user_id');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        $user = $table->getUser($id);
        if (! isset($user)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }

        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isInstanceOf($nowMessenger, NowMessenger::class);
        $form = $this->editUserForm;
        $form->prepareForEdit();
        $form->setData($user);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data          = $request->getPost()->toArray();
            $isPersonIdSet = isset($data['personId']);
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (! $isPersonIdSet) {
                    unset($data['personId']);
                }

                $this->logger->info("Updating user", ['userId' => $id, 'data' => $data]);

                if ($table->updateEntity('user', $id, $data)) {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('User successfully updated.');
                    return $this->redirect()->toUrl($this->url()->fromRoute('juser'));
                } else {
                    $this->logger->crit(
                        "We submitted a user update, but something went wrong",
                        ['userId' => $id, 'data' => $data]
                    );
                    $nowMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
                    $nowMessenger->addMessage('Error in form submission, please review.');
                }
            } else {
                $messages = $form->getMessages();
                $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
                $nowMessenger->addMessage(
                    'Error in form submission, please review: ' . implode(', ', array_keys($messages))
                );
            }
        }
        $changePasswordForm = $this->changeOtherPasswordForm;
        $deleteUserForm     = new DeleteUserForm();

        return new ViewModel([
            'userId'             => $id,
            'user'               => $user,
            'form'               => $form,
            'changePasswordForm' => $changePasswordForm,
            'deleteUserForm'     => $deleteUserForm,
        ]);
    }

    public function createAction(): ModelInterface|ResponseInterface
    {
        $table = $this->userTable;
        $form  = $this->editUserForm;

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
                    if (! $table->createEntity('user', $data)) {
                        $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                            ->addMessage('Error in form submission, please review.');
                    } else {
                        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                            ->addMessage('User successfully created.');
                        return $this->redirect()->toRoute('juser');
                    }
                } catch (Exception $e) {
                    $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                        ->addMessage('Error in form submission, please review.');
                }
            } else {
                $this->nowMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        } else {
            $rolesList    = $form->get('rolesList');
            $defaultRoles = $table->getDefaultRoles();
            $rolesList->setValue(array_keys($defaultRoles));
        }
        return new ViewModel([
            'form' => $form,
        ]);
    }

    protected function hashPassword(string $password): string
    {
        Assert::notEmpty($password);
        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->lmcModuleOptions->getPasswordCost());
        return $bcrypt->create($password);
    }

    public function createRoleAction(): ModelInterface|ResponseInterface
    {
        $table   = $this->userTable;
        $form    = $this->createRoleForm;
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
                } catch (Exception $e) {
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

    public function deleteAction(): ViewModel|Response
    {
        $id = (int) $this->params('user_id');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }
        $table = $this->userTable;
        if (! $table->existsEntity('user', $id)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('User not found.');
            return $this->redirect()->toRoute('juser');
        }

        $form    = new DeleteUserForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $result = $table->deleteUser($id);
                Assert::eq($result, 1);
            } else {
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('User not found.');
            }
            // Redirect to list of users
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                ->addMessage('User deleted.');
            return $this->redirect()->toRoute('juser');
        }
        $userIdData = ['userId' => $id];
        $form->setData($userIdData);
        $user = $table->getUser($id);

        return new ViewModel([
            'userId' => $id,
            'user'   => $user,
            'form'   => $form,
        ]);
    }

    protected static function setNewVerificationToken(
        array $user,
        string $expirationInterval = self::VERIFICATION_TOKEN_EXPIRATION_INTERVAL
    ): array {
        $user['verificationToken'] = User::generateVerificationToken();
        $dt                        = new DateTime('now', new DateTimeZone('UTC'));
        $dt->add(new DateInterval($expirationInterval));
        $user['verificationExpiration'] = $dt;
        return $user;
    }
}
