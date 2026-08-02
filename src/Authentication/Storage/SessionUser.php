<?php

namespace JUser\Authentication\Storage;

use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Authentication\Storage\StorageInterface;

/**
 * Authentication storage that keeps only the user id in the session and resolves
 * it to a JUser\Model\User entity on read.
 *
 * Modeled on the former ZfcUser\Authentication\Storage\Db, minus the ZfcUser types.
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

        if ($identity instanceof User) {
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
