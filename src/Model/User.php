<?php

namespace JUser\Model;

use ZfcUser\Entity\UserInterface;
use Zend\Math\Rand;

class User implements UserInterface
{
    //@todo make length configurable
    const VERIFICATION_TOKEN_LENGTH = 32;
    /**
     * Id 0 means to be inserted
     * @var int $id
     */
    public $id = 0;

    /**
     * @var string $username
     */
    public $username;

    /**
     * @var string $email
     */
    public $email;

    /**
     * @var string $displayName
     */
    public $displayName;

    /**
     * @var string $password
     */
    public $password;

    /**
     * One state must mean change password
     * @var int $state
     */
    public $state = 0;
    
    /**
     * Force user to change password if this bit is set
     * @var bool $mustChangePassword
     */
    public $mustChangePassword = false;
    
    /**
     * 32-character random alphanumeric nonce for verifying email addresses
     * @var string $verificationToken
     */
    public $verificationToken;
    
    /**
     * The time when the token will no longer be valid
     * @var \DateTime $verificationExpiration
     */
    public $verificationExpiration;
    
    /**
     * denotes a user used by multiple people, these shouldn't be able to change the password
     * @var bool $multiPersonUser
     */
    public $multiPersonUser = false;
    
    /**
     * @var string $updateDatetime
     */
    public $updateDatetime;
    
    /**
     * @var string $createDatetime
     */
    public $createDatetime;
    
    public $roles;
    
    public $rolesList;

    public function __construct($data = [])
    {
        if (is_array($data) && !empty($data)) {
            $this->exchangeArray($data);
        }
    }
    
    public function exchangeArray($data)
    {
        $this->id = isset($data['userId']) ? $data['userId'] : null;
        $this->username = isset($data['username']) ? $data['username'] : null;
        $this->email = isset($data['email']) ? $data['email'] : null;
        $this->displayName = isset($data['displayName']) ? $data['displayName'] : null;
        $this->password = isset($data['password']) ? $data['password'] : null;
        $this->state = isset($data['active']) ? $data['active'] : null;
        $this->mustChangePassword = isset($data['mustChangePassword']) ? $data['mustChangePassword'] : null;
        $this->multiPersonUser = isset($data['isMultiPersonUser']) ? $data['isMultiPersonUser'] : null;
        $this->verificationToken = isset($data['verificationToken']) ? $data['verificationToken'] : null;
        $this->verificationExpiration = $data['verificationExpiration'];
        $this->createDatetime = isset($data['createdOn'])
            ? $data['createdOn']->format('Y-m-d H:i:s') : null;
        $this->updateDatetime = isset($data['updatedOn'])
            ? $data['updatedOn']->format('Y-m-d H:i:s') : null;
        $this->roles = isset($data['roles']) ? $data['roles'] : null;
        $this->rolesList = isset($data['rolesList']) ? $data['rolesList'] : null;
//         'createdBy'         => $this->filterDbInt($row['create_by']),
//         'updatedBy'         => $this->filterDbInt($row['update_by']),
//         'emailVerified'     => $this->filterDbBool($row['email_verified']),
//            'languages'         => $this->filterDbArray($row['lang'], ';'),
//         'personId'          => $this->filterDbId($row['PersID']),
//         'roles'             => [],
//         'rolesList'         => [],
    }
    
    public function getArrayCopy()
    {
        $data = [
            'userId'            => $this->id,
            'username'          => $this->username,
            'email'             => $this->email,
            'displayName'       => $this->displayName,
            'password'          => $this->password,
            'createdOn'         => $this->createDatetime,
//             'createdBy'         => $this->filterDbInt($row['create_by']),
            'updatedOn'         => $this->updateDatetime,
//             'updatedBy'         => $this->filterDbInt($row['update_by']),
//             'emailVerified'     => $this->filterDbBool($row['email_verified']),
            'mustChangePassword'=> $this->mustChangePassword,
            'isMultiPersonUser' => $this->multiPersonUser,
            'verificationToken' => $this->verificationToken,
            'verificationExpiration' => $this->verificationExpiration,
            'active'            => $this->state,
            'roles'             => isset($this->roles) ? $this->roles : [],
            'rolesList'         => isset($this->rolesList) ? $this->rolesList : [],
//            'languages'         => $this->filterDbArray($row['lang'], ';'),
//             'personId'          => $this->filterDbId($row['PersID']),
        ];
        return $data;
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
    
    /**
     * Get the verificationToken value
     * @return string
     */
    public function getVerificationToken()
    {
        if (!isset($this->verificationToken)) {
            //if we don't have one, make one up (mainly for registration)
            $this->verificationToken = static::generateVerificationToken();
        }
        return $this->verificationToken;
    }
    
    /**
     * Generate a verification token
     * @return void
     */
    public static function generateVerificationToken()
    {
        static $charList;
        if (!isset($charList)) {
            $charList = implode('', array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9')));
        }
        $verificationToken = Rand::getString(self::VERIFICATION_TOKEN_LENGTH, $charList);
        return $verificationToken;
    }
    
    /**
     * Set the verificationToken value
     * @param string $verificationToken
     * @return self
     */
    public function setVerificationToken(?string $verificationToken)
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }
    
    /**
     * Get the verificationExpiration date value 
     * @return \DateTime
     */
    public function getVerificationExpiration()
    {
        if (!isset($this->verificationExpiration)) {
            //if we don't have one, make one up (mainly for registration)
            //@todo make sure in the end this can't be leveraged in some clever way that the user can set their own
            $this->resetVerificationExpiration();
        }
        return $this->verificationExpiration;
    }
    
    public function isVerificationTokenValid()
    {
        if (!isset($this->verificationExpiration) || !$this->verificationExpiration instanceof \DateTime) {
            return false;
        }
        $now = new \DateTime(null, new \DateTimeZone('UTC'));
        return $now <= $this->verificationExpiration;
    }
    
    /**
     * Force the reset of the verification token expiration
     * @return self
     */
    public function resetVerificationExpiration()
    {
        $dt = new \DateTime(null, new \DateTimeZone('UTC'));
        //@todo make interval configurable
        $dt->add(new \DateInterval('P1D'));
        $this->verificationExpiration = $dt;
        return $this;
    }
    
    /**
     * Set the verificationExpiration value
     * @param \DateTime $verificationExpiration
     * @return self
     */
    public function setVerificationExpiration($verificationExpiration)
    {
        $this->verificationExpiration = $verificationExpiration;
        return $this;
    }
    
    /**
     * Get the updateDatetime value
     * @return string
     */
    public function getUpdateDatetime()
    {
        if (!isset($this->updateDatetime)) {
            $dt = new \DateTime(null, new \DateTimeZone('UTC'));
            $this->updateDatetime = $dt->format('Y-m-d H:i:s');
        }
        return $this->updateDatetime;
    }
    
    /**
     * Set the updateDatetime value
     * @param string $updateDatetime
     * @return self
     */
    public function setUpdateDatetime($updateDatetime)
    {
        $this->updateDatetime = $updateDatetime;
        return $this;
    }
    
    /**
     * Get the createDatetime value
     * @return string
     */
    public function getCreateDatetime()
    {
        if (!isset($this->createDatetime)) {
            $dt = new \DateTime(null, new \DateTimeZone('UTC'));
            $this->createDatetime = $dt->format('Y-m-d H:i:s');
        }
        return $this->createDatetime;
    }
    
    /**
     * Set the createDatetime value
     * @param string $createDatetime
     * @return self
     */
    public function setCreateDatetime($createDatetime)
    {
        $this->createDatetime = $createDatetime;
        return $this;
    }
    
    /**
     * Generate and set a new verification token and reset the expiration for a day from now
     * @return self
     */
    public function setNewVerificationToken()
    {
        $this->verificationToken = null;
        $this->getVerificationToken();
        $this->resetVerificationExpiration();
        return $this;
    }
}
