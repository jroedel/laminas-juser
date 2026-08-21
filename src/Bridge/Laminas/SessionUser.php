<?php

namespace JUser\Bridge\Laminas;

use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Authentication\Storage\StorageInterface;

/**
 * Authentication storage that keeps only the user id in the session and resolves
 * it to a JUser\Model\User entity on read.
 *
 * Modeled on the former ZfcUser\Authentication\Storage\Db, minus the ZfcUser types.
 *
 * **Only the id is in the session, so the row is re-read on every request** — and that is
 * what makes this the place where a deactivation takes effect on a session that is already
 * open. See read(). Without it, `state = 0` would mean "cannot sign in again" rather than
 * "cannot act", and an administrator revoking access to a compromised account would be
 * waiting on a session timeout they cannot see or influence.
 */
class SessionUser implements StorageInterface
{
    /** @var StorageInterface $storage */
    protected $storage;

    /** @var UserTable $userTable */
    protected $userTable;

    /** @var User|null $resolvedIdentity */
    protected $resolvedIdentity;

    public function __construct(UserTable $userTable, ?StorageInterface $storage = null)
    {
        $this->userTable = $userTable;
        if (null !== $storage) {
            $this->storage = $storage;
        }
    }

    /**
     * Returns true if and only if storage is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        if ($this->getStorage()->isEmpty()) {
            return true;
        }
        if (null === $this->read()) {
            $this->clear();
            return true;
        }
        return false;
    }

    /**
     * Returns the contents of storage, resolved to a User entity
     *
     * A **deactivated account resolves to null**, which is the fourth and last place
     * `user`.`state` is enforced, and the only one that reaches a session already open.
     * The other three all happen at the door: no link is mailed, a link in flight is
     * refused, and a bearer token is refused. This one is why `state = 0` means "cannot
     * act" and not merely "cannot sign in again".
     *
     * Nothing extra had to be built for the consequences: isEmpty() already treats a null
     * read as empty *and clears the storage*, so the next request of a deactivated
     * account's session is anonymous and the session no longer carries an identity to
     * resolve. That behaviour was there for a deleted account, which is the same problem
     * one step further along.
     *
     * The cost is one read of a column already in the row this method fetches, on a lookup
     * that was happening anyway.
     *
     * @return User|null
     */
    public function read()
    {
        if (null !== $this->resolvedIdentity) {
            return $this->resolvedIdentity;
        }

        $identity = $this->getStorage()->read();

        if (is_scalar($identity)) {
            $identity = $this->userTable->findById($identity);
        }

        if ($identity instanceof User && 1 == $identity->getState()) {
            $this->resolvedIdentity = $identity;
        } else {
            $this->resolvedIdentity = null;
        }

        return $this->resolvedIdentity;
    }

    /**
     * Writes $contents to storage. Only the user id should be written.
     *
     * @param mixed $contents
     * @return void
     */
    public function write($contents)
    {
        $this->resolvedIdentity = null;
        if ($contents instanceof User) {
            $contents = (int) $contents->getId();
        }
        $this->getStorage()->write($contents);
    }

    /**
     * Clears contents from storage
     *
     * @return void
     */
    public function clear()
    {
        $this->resolvedIdentity = null;
        $this->getStorage()->clear();
    }

    /**
     * @return StorageInterface
     */
    public function getStorage()
    {
        if (null === $this->storage) {
            $this->setStorage(new SessionStorage());
        }
        return $this->storage;
    }

    /**
     * @param StorageInterface $storage
     * @return self
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->storage = $storage;
        return $this;
    }
}
