<?php

declare(strict_types=1);

namespace JUser\Model;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Laminas\Math\Rand;
use LmcUser\Entity\UserInterface;

use function array_merge;
use function implode;
use function is_array;
use function range;

class User implements UserInterface
{
    //@todo make length configurable
    public const VERIFICATION_TOKEN_LENGTH = 32;
    /**
     * Id 0 means to be inserted
     */
    private int $id = 0;

    private string $username;

    private string $email;

    private string $displayName;

    private string $password;

    /**
     * One state must mean change password
     */
    private int $state = 0;

    /**
     * Force user to change password if this bit is set
     */
    private bool $mustChangePassword = false;

    /**
     * 32-character random alphanumeric nonce for verifying email addresses
     */
    private ?string $verificationToken = null;

    /**
     * The time when the token will no longer be valid
     */
    private ?DateTimeInterface $verificationExpiration = null;

    /**
     * denotes a user used by multiple people, these shouldn't be able to change the password
     */
    private bool $multiPersonUser = false;

    /** @var string $updateDatetime */
    private $updateDatetime;

    /** @var string $createDatetime */
    private $createDatetime;

    private $roles;

    private $rolesList;

    public function __construct(array $data = [])
    {
        if (is_array($data) && ! empty($data)) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray(array $data): void
    {
        $this->id                     = isset($data['userId']) ? (int) $data['userId'] : null;
        $this->username               = $data['username'] ?? null;
        $this->email                  = $data['email'] ?? null;
        $this->displayName            = $data['displayName'] ?? null;
        $this->password               = $data['password'] ?? null;
        $this->state                  = $data['active'] ?? 0;
        $this->mustChangePassword     = $data['mustChangePassword'] ?? false;
        $this->multiPersonUser        = $data['isMultiPersonUser'] ?? false;
        $this->verificationToken      = $data['verificationToken'] ?? null;
        $this->verificationExpiration = $data['verificationExpiration'];
        $this->createDatetime         = isset($data['createdOn'])
            ? $data['createdOn']->format('Y-m-d H:i:s') : null;
        $this->updateDatetime         = isset($data['updatedOn'])
            ? $data['updatedOn']->format('Y-m-d H:i:s') : null;
        $this->roles                  = $data['roles'] ?? null;
        $this->rolesList              = $data['rolesList'] ?? null;
    }

    public function getArrayCopy(): array
    {
        return [
            'userId'                 => $this->id,
            'username'               => $this->username,
            'email'                  => $this->email,
            'displayName'            => $this->displayName,
            'password'               => $this->password,
            'createdOn'              => $this->createDatetime,
            'updatedOn'              => $this->updateDatetime,
            'mustChangePassword'     => $this->mustChangePassword,
            'isMultiPersonUser'      => $this->multiPersonUser,
            'verificationToken'      => $this->verificationToken,
            'verificationExpiration' => $this->verificationExpiration,
            'active'                 => $this->state,
            'roles'                  => $this->roles ?? [],
            'rolesList'              => $this->rolesList ?? [],
//            'languages'         => $this->filterDbArray($row['lang'], ';'),
//             'personId'          => $this->filterDbId($row['PersID']),
        ];
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get username.
     */
    public function getUsername(): string
    {
        return $this->username;
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
     * Get state.
     *
     * @return int
     */
    public function getState()
    {
        return $this->state;
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
     * Get multi person user bit
     *
     * @return bool
     */
    public function getMultiPersonUser()
    {
        return $this->multiPersonUser;
    }

    /**
     * Get the verificationToken value
     *
     * @return string
     */
    public function getVerificationToken()
    {
        if (! isset($this->verificationToken)) {
            //if we don't have one, make one up (mainly for registration)
            $this->verificationToken = static::generateVerificationToken();
        }
        return $this->verificationToken;
    }

    /**
     * Generate a verification token
     */
    public static function generateVerificationToken(int $tokenLength = self::VERIFICATION_TOKEN_LENGTH): string
    {
        static $charList;
        if (! isset($charList)) {
            $charList = implode('', array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9')));
        }
        return Rand::getString($tokenLength, $charList);
    }

    /**
     * Get the verificationExpiration date value
     */
    public function getVerificationExpiration(): DateTimeInterface|null
    {
        if (! isset($this->verificationExpiration)) {
            //if we don't have one, make one up (mainly for registration)
            //@todo make sure in the end this can't be leveraged in some clever way that the user can set their own
            $this->resetVerificationExpiration();
        }
        return $this->verificationExpiration;
    }

    public function isVerificationTokenValid(): bool
    {
        if (! isset($this->verificationExpiration) || ! $this->verificationExpiration instanceof DateTime) {
            return false;
        }
        $now = new DateTime('now', new DateTimeZone('UTC'));
        return $now <= $this->verificationExpiration;
    }

    /**
     * Force the reset of the verification token expiration
     */
    public function resetVerificationExpiration(): void
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        //@todo make interval configurable
        $dt->add(new DateInterval('P1D'));
        $this->verificationExpiration = $dt;
    }

    /**
     * Get the updateDatetime value
     *
     * @return string
     */
    public function getUpdateDatetime()
    {
        if (! isset($this->updateDatetime)) {
            $dt                   = new DateTime('now', new DateTimeZone('UTC'));
            $this->updateDatetime = $dt->format('Y-m-d H:i:s');
        }
        return $this->updateDatetime;
    }

    /**
     * Get the createDatetime value
     *
     * @return string
     */
    public function getCreateDatetime()
    {
        if (! isset($this->createDatetime)) {
            $dt                   = new DateTime('now', new DateTimeZone('UTC'));
            $this->createDatetime = $dt->format('Y-m-d H:i:s');
        }
        return $this->createDatetime;
    }

    /**
     * Generate and set a new verification token and reset the expiration for a day from now
     *
     * @return self
     */
    public function setNewVerificationToken()
    {
        $this->verificationToken = null;
        $this->getVerificationToken();
        $this->resetVerificationExpiration();
        return $this;
    }

    /**
     * @param int $id
     */
    public function setId($id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param string $username
     * @return User
     */
    public function setUsername($username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @param string $email
     * @return User
     */
    public function setEmail($email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @param string $password
     * @return User
     */
    public function setPassword($password): User
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @param int $state
     * @return User
     */
    public function setState($state): User
    {
        $this->state = $state;
        return $this;
    }
}
