<?php

namespace JUser\Entity;

use ZfcUser\Entity\UserInterface;

class User implements UserInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $displayName;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var int
     */
    protected $state;
    
    /**
     * Semicolon separated ordered list of languages
     * @var string
     */
    protected $lang;
    
    /**
     * Force user to change password if this bit is set
     * @var bool
     */
    protected $mustChangePassword;

    /**
     * denotes a user used by multiple people, these shouldn't be able to change the password
     * @var bool
     */
    protected $multiPersonUser;
    
    /**
     * @var \DateTime
     */
    protected $updateDatetime;

    /**
     * @var \DateTime
     */
    protected $createDatetime;
    
    public function __construct()
    {
        $this->createDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
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
        $this->updateDatetime = new \DateTime(null, new \DateTimeZone('UTC'));
        return $this;
    }
}
