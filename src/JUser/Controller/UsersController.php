<?php

namespace JUser\Controller;

use Zend\Mail\Message;

use Zend\Mail\Transport\TransportInterface;

use JUser\Form\EditUserForm;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use JUser\Form\UserForm;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Form\ChangeOtherPasswordForm;
use JUser\Form\DeleteUserForm;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Zend\Crypt\Password\Bcrypt;
use Patres\Model\PatresTable;

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
    protected $transport;
    protected $message;

    public function setMailTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function setMessage(Message $message)
    {
        $this->message = $message;
    }
    
    /**
     *
     * @return UserTable
     */
    public function setUserTable(UserTable $userTable)
    {
        $this->userTable = $userTable;
    }
    
    public function changePasswordAction()
    {
    	$id = (int)$this->params('user_id');
    	if (!$id) {
    		$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
    		return $this->redirect()->toRoute('juser');
    	}
    	/** @var UserTable $table */
    	$table = $this->getServiceLocator()->get('JUser\Model\UserTable');
    	$form = new ChangeOtherPasswordForm();
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
		    	$zfcOptions = $this->getServiceLocator()->get('zfcuser_module_options');
		    	$bcrypt = new Bcrypt();
		        $bcrypt->setCost($zfcOptions->getPasswordCost());
		        $pass = $bcrypt->create($data['newCredential']);
// 		        var_dump($pass);
		        $table->updateUserPassword($id, $pass);
    			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'User password updated successfully.' );
	     		return $this->redirect()->toRoute('juser');
    		} else {
    			$this->nowMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Please review the form and resubmit.' );
    		}
    	} else {
    		$userIdData = array('userId' => $id);
    		$form->setData($userIdData);
    	}
    	$user = $table->getUser($id);
    	
    	return array(
    			'userId' => $id,
    			'user' => $user,
    			'form' => $form,
    	);
    }
    
    public function indexAction()
    {
        /** @var PatresTable $table */
        $table = $this->getServiceLocator()->get('Patres\Model\PatresTable');
        //get course persons
        $query = array(
            'deceased'     => true,
            'exMembers'    => true,
        );
        $persons = $table->searchPersons($query, false, true);
//         var_dump($persons);
//         $data = array(
//             'from' => 'mountsiondata@gmail.com',
//             'subject' => 'viewing',
//             'body' => 'Someone is looking at the users.'
//         );
//         $this->sendEmail($data);
        
//         try {
//             if (!$this->isAllowed('user', 'read')) {
//                 throw new \Exception('You do not have permission to view this page.');
//             }
//         }
//         catch (\Exception $e) {
//             $this->flashMessenger()->setNamespace('error')->addMessage('404');
//             return $this->redirect()->toRoute('juser');
//         }
        $users = $this->userTable->getUsers();
        return new ViewModel(array(
            'users' => $users,
            'persons' => $persons
        ));
    }
    
    public function editAction()
    {
		$sm = $this->getServiceLocator ();
		/** @var UserTable $table **/
		$table = $sm->get ( 'JUser\Model\UserTable' );
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
		$form = $sm->get('JUser\Form\EditUserForm');
		$form->setData($user);
		$request = $this->getRequest ();
		if ($request->isPost ()) {
			$data = $request->getPost ()->toArray ();
			if ($data ['userId'] != $id) { // make sure the user is trying to update the right event
				$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please try again later.' );
				return $this->redirect ()->toRoute ( 'juser' );
			}
			$form->setData ( $data );
			if ($form->isValid ()) {
			    $data = $form->getData();
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
        $userIdData = array('userId' => $id);
        $changePasswordForm = new ChangeOtherPasswordForm();
        $changePasswordForm->setData($userIdData);
        $deleteUserForm = new DeleteUserForm();
        $deleteUserForm->setData($userIdData);

        return new ViewModel(array(
            'userId' => $id,
            'user' => $user,
            'form' => $form,
        	'changePasswordForm' => $changePasswordForm,
        	'deleteUserForm' => $deleteUserForm,
        ));
    }

    public function createAction()
    {
		$sm = $this->getServiceLocator ();
		/** @var UserTable $table **/
		$table = $sm->get ( 'JUser\Model\UserTable' );

        /** @var EditUserForm $form */
        $form = $sm->get('JUser\Form\EditUserForm');
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
        $zfcOptions = $this->getServiceLocator()->get('zfcuser_module_options');
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
        $sm = $this->getServiceLocator ();
        /** @var UserTable $table **/
        $table = $sm->get ( 'JUser\Model\UserTable' );
    
        /** @var EditUserForm $form */
        $form = $sm->get('JUser\Form\CreateRoleForm');
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
        return array (
            'form' => $form,
        );
    }
    
    public function deleteAction()
    {
        $id = (int)$this->params('user_id');
        if (!$id) {
			$this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'User not found.' );
            return $this->redirect()->toRoute('juser');
        }

        $table = $this->getServiceLocator()->get('JUser\Model\UserTable');
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
        	$userIdData = array('userId' => $id);
        	$form->setData($userIdData);
        }
        $user = $table->getUser($id);
    
        return array(
            'userId' => $id,
        	'user' => $user,
            'form' => $form,
        );
    }

    protected function sendEmail(array $data)
    {
        $from    = $data['from'];
        $subject = '[Testing Users] ' . $data['subject'];
        $body    = $data['body'];
    
        $this->message->addFrom($from)
        ->addReplyTo($from)
        ->setSubject($subject)
        ->setBody($body);
        $this->transport->send($this->message);
    }
}