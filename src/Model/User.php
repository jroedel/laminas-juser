<?php

namespace JUser\Model;

use ZfcUser\Entity\UserInterface;

class User implements UserInterface
{
    /**
     * Id 0 means to be inserted
     * @var int
     */
    public $id = 0;

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $displayName;

    /**
     * @var string
     */
    public $password;

    /**
     * One state must mean change password
     * @var int
     */
    public $state;
    
    /**
     * Force user to change password if this bit is set
     * @var bool
     */
    public $mustChangePassword;

    /**
     * denotes a user used by multiple people, these shouldn't be able to change the password
     * @var bool
     */
    public $multiPersonUser;
    
    public $updateDateTime;
    
    public $createDateTime;
    
    public $roles = null;
    
    /**
     * Indicates if we have loaded the roles yet
     * @var bool
     */
    public $rolesLoaded = false;
    
    //I don't think this is used
//     public function exchangeArray($data)
//     {
//         $this->id  = (isset($data['user_id'])) ? $data['user_id'] : null;
//         $this->username  = (isset($data['username'])) ? $data['username'] : null;
//         $this->email  = (isset($data['email'])) ? $data['email'] : null;
//         $this->displayName  = (isset($data['display_name'])) ? $data['display_name'] : null;
//         $this->password  = (isset($data['password'])) ? $data['password'] : null;
//         $this->state  = (isset($data['state'])) ? $data['state'] : null;
//         $this->mustChangePassword  = (isset($data['must_change_password'])) ? (bool)$data['must_change_password'] : false;
//         $this->multiPersonUser  = (isset($data['multi_person_user'])) ? (bool)$data['multi_person_user'] : false;
//         $this->updateDateTime = (isset($data['update_datetime'])) ? new \DateTime($data['update_datetime'], new \DateTimeZone('UTC')) : null;
//         $this->createDateTime = (isset($data['create_datetime'])) ? new \DateTime($data['create_datetime'], new \DateTimeZone('UTC')) : null;
//     }
    
    /**
     * Magic getter to expose protected properties.
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->$property;
    }
    /**
     * Magic setter to save protected properties.
     *
     * @param string $property
     * @param mixed $value
     */
    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        throw new \Exception("Not used");
    }
    
    public function getInputFilter()
    {
        //put stuff here
    }
    

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set id.
     *
     * @param int $id
     * @return UserInterface
     */
    public function setId($id)
    {
        $this->id = (int) $id;
        return $this;
    }
    
    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }
    
    /**
     * Set username.
     *
     * @param string $username
     * @return UserInterface
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }
    
    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }
    
    /**
     * Set email.
     *
     * @param string $email
     * @return UserInterface
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }
    
    /**
     * Get displayName.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }
    
    /**
     * Set displayName.
     *
     * @param string $displayName
     * @return UserInterface
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
        return $this;
    }
    
    /**
     * Get password.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }
    
    /**
     * Set password.
     *
     * @param string $password
     * @return UserInterface
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }
    
    /**
     * Get state.
     *
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }
    
    /**
     * Set state.
     *
     * @param int $state
     * @return UserInterface
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * Get must change password status.
     *
     * @return bool
     */
    public function getMustChangePassword()
    {
        return $this->mustChangePassword;
    }
    
    /**
     * Set mustChangePassword.
     *
     * @param bool $mustChangePassword
     * @return UserInterface
     */
    public function setMustChangePassword($mustChangePassword)
    {
        $this->mustChangePassword = (bool)$mustChangePassword;
        return $this;
    }
    
    /**
     * Get multi person user bit
     *
     * @return bool
     */
    public function getMultiPersonUser()
    {
        return $this->multiPersonUser;
    }
    
    /**
     * Set multiPersonUser.
     *
     * @param bool $multiPersonUser
     * @return UserInterface
     */
    public function setMultiPersonUser($multiPersonUser)
    {
        $this->multiPersonUser = (bool)$multiPersonUser;
        return $this;
    }
}
