<?php

namespace JUser\Controller;

use JUser\Form\EditUserForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Form\ChangeOtherPasswordForm;
use JUser\Form\DeleteUserForm;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Zend\Crypt\Password\Bcrypt;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Form\CreateRoleForm;

/**
 *
 * @author Jeff Roedel <jeff.roedel@gmail.com>
 * @todo   fix activation
 * @todo   user email verification
 * @todo   email admins to alert new user request
 */
class UsersController extends AbstractActionController
{
    protected $userTable;
    
    protected $services = [];
    
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
    
    public function changePasswordAction()
    {
    	$id = (int)$this->params('user_id');
    	if (!$id) {
    		$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
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
    			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission.' );
	     		return $this->redirect()->toRoute('juser');
    		}
    		$form->setData($data);
    		if ($form->isValid()) {
    			$data = $form->getData();
		    	$bcrypt = new Bcrypt();
		        $bcrypt->setCost($zfcOptions->getPasswordCost());
		        $pass = $bcrypt->create($data['newCredential']);
		        $table->updateUserPassword($id, $pass);
    			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'User password updated successfully.' );
	     		return $this->redirect()->toRoute('juser');
    		} else {
    			$this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Please review the form and resubmit.' );
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
            	   throw new \InvalidArgumentException('`person_provider` specified in the JUser config does not implement the PersonValueOptionsProviderInterface.');
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
		$id = ( int ) $this->params ()->fromRoute ( 'user_id' );
		if (! $id) {
			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
			return $this->redirect ()->toRoute ( 'juser' );
		}
		$user  = $table->getUser ( $id );
		if (! $user) {
			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
			return $this->redirect ()->toRoute ( 'juser' );
		}

		/** @var EditUserForm $form */
		$form = $this->getService(EditUserForm::class);
		$form->setData($user);
		$request = $this->getRequest ();
		if ($request->isPost ()) {
			$data = $request->getPost ()->toArray ();
			if ($data ['userId'] != $id) { // make sure the user is trying to update the right event
				$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please try again later.' );
				return $this->redirect ()->toRoute ( 'juser' );
			}
			$isPersonIdSet = key_exists('personId', $data);
			$form->setData ( $data );
			if ($form->isValid ()) {
			    $data = $form->getData();
			    if (!$isPersonIdSet) {
			        unset($data['personId']);
			    }
				if ($table->updateUser ($id, $data)) {
					$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'User successfully updated.' );
					$this->redirect ()->toUrl ( $this->url ()->fromRoute ( 'juser' ) );
				} else {
					$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
				}
			} else {
				$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
			}
		}
        $userIdData = ['userId' => $id];
        $changePasswordForm = new ChangeOtherPasswordForm();
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
        $form->setValidatorsForCreate();
        $form->setName('create_user');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (isset($data['password']) && $data['password']) { //the validators should've already confirmed the passwordVerify field
                    $data['password'] = $this->hashPassword($data['password']);
                }
                try {
                    if (!($newId = $table->createUser($data))) {
                        $this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                    } else {
                        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'User successfully created.' );
                        $this->redirect ()->toRoute ( 'juser');
                    }
                } catch (\Exception $e) {
                    $this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            } else {
                $this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return array (
            'form' => $form,
        );
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
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $table->createRole($data);
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Role successfully created.' );
                    $this->redirect ()->toRoute ( 'juser');
                } catch (\Exception $e) {
                    $this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            } else {
                $this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return [
            'form' => $form,
        ];
    }

    public function deleteAction()
    {
        $id = (int)$this->params('user_id');
        if (!$id) {
			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
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
					$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Database error deleting user. '.$result );
				}
			} else {
				$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
			}
	        // Redirect to list of users
			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'User deleted.' );
	        return $this->redirect()->toRoute('juser');
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
}