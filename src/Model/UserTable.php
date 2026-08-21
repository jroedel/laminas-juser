<?php

namespace JUser\Model;

use Laminas\Db\Sql\Select;
use SionModel\Db\Model\SionTable;
use JUser\Service\Mailer;

class UserTable extends SionTable
{
    public const USER_TABLE_NAME = 'user';
    public const ROLE_TABLE_NAME = 'user_role';
    public const USER_ROLE_LINKER_TABLE_NAME = 'user_role_linker';

    /** Maximum length of the username column */
    public const USERNAME_MAX_LENGTH = 255;

    /** Maximum length of the display_name column */
    public const DISPLAY_NAME_MAX_LENGTH = 50;

    /** @var Mailer $mailer */
    protected $mailer;

    protected $flashMessenger;

    /**
     * Look up a user by email address.
     *
     * NOTE: this is a pure lookup. It deliberately doesn't send any mail;
     * the passwordless login flow (@see \JUser\Service\LoginTokenService) owns
     * all sign-in messaging so that a lookup can never double-send.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail($email)
    {
        if ($this->logger) {
            $this->logger->debug("JUser: Looking up user by email", ['email' => $email]);
        }
        $results = $this->queryObjects('user', ['email' => $email]);
        if (! isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }

        //@todo trigger find event
        return $userObject;
    }

    /**
     * Look up a user by username. @see self::findByEmail() about messaging.
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername($username)
    {
        if ($this->logger) {
            $this->logger->debug("JUser: Looking up user by username", ['username' => $username]);
        }
        $results = $this->queryObjects('user', ['username' => $username]);
        if (! isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }

        //@todo trigger find event
        return $userObject;
    }

    /**
     * @param string|int $id
     * @return User|null
     */
    public function findById($id)
    {
        if ($this->logger) {
            $this->logger->debug("JUser: Looking up user by id", ['id' => $id]);
        }
        $userArray = $this->getUser($id);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }
        //@todo trigger find event
        return $userObject;
    }

    /**
     * @param User $user
     */
    public function updateUser(User $user)
    {
        $data = $user->getArrayCopy();
        if (isset($this->logger)) {
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
            $this->logger->info("About to update a user.", ['caller' => $caller, 'user' => $user]);
        }
        $result = $this->updateEntity('user', $data['userId'], $data);
        if (false === $result) {
            if (isset($this->logger)) {
                $this->logger->error("Failed updating a user.", ['result' => $result, 'user' => $user]);
            }
            throw new \Exception('Error inserting a new user.');
        } else {
            if (isset($this->logger)) {
                $this->logger->info("Finished updating a user.", ['result' => $result]);
            }
        }
        return $result;
    }

    /**
     * Gets list of users
     *
     * @return mixed[]
     */
    public function getUsers(array $ids = [])
    {
        if (empty($ids)) {
            $cacheKey = 'all-linked-users';
            if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
                return $cache;
            }
        }
        $query = [];
        if (! empty($ids)) {
            if (1 === count($ids)) {
                $query['userId'] = current($ids);
            } else {
                $query['userId'] = $ids;
            }
        }
        $objects = $this->queryObjects('user', $query);
        $this->linkUsers($objects);

        if (isset($cacheKey)) {
            $this->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role', 'user-role-link']);
        }
        return $objects;
    }

    /**
     * Add role data to an array of user arrays (the array must be keyed on the userId)
     * @param array $users
     */
    public function linkUsers(array &$users)
    {
        if (empty($users)) {
            return;
        }

        $userIds = array_keys($users);
        if (count($users) < 20) {
            //first compile list of user id to get just the rows we need
            $query = ['userId' => $userIds];
        } else {
            //if we're looking at several users, just get all records, it's better for caching
            $query = [];
        }

        $roleLinks = $this->queryObjects('user-role-link', $query);
        foreach ($roleLinks as $link) {
            if (isset($users[$link['userId']])) {
                $users[$link['userId']]['roles'][$link['roleId']] = $link;
            }
        }

        foreach ($userIds as $id) {
            $users[$id]['rolesList'] = array_keys($users[$id]['roles']);
        }
    }

    public function linkUser(array &$user)
    {
        $roleLinks = $this->queryObjects('user-role-link', ['userId' => $user['userId']]);
        foreach ($roleLinks as $link) {
            $user['roles'][$link['roleId']] = $link;
        }

        $user['rolesList'] = array_keys($user['roles']);
    }

    /**
     * Get an associative array of giving the username of each userId in the user table
     * @param array $ids
     * @return string[]
     */
    public function getUsernames(array $ids = [])
    {
        if (empty($ids)) {
            $cacheKey = 'usernames';
            if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
                return $cache;
            }
        }
        $query = [];
        if (! empty($ids)) {
            $query['userId'] = $ids;
        }
        $objects = $this->getObjects('user', $query);
        //manipulate results
        $usernames = [];
        foreach ($objects as $object) {
            $usernames[$object['userId']] = $object['username'];
        }

        if (empty($ids)) {
            $this->cacheEntityObjects($cacheKey, $usernames, ['user']);
        }
        return $usernames;
    }

    protected function processUserRow($row)
    {
        $processedRow = [
            'userId'            => $row['user_id'],
            'username'          => $row['username'],
            'email'             => $row['email'],
            'displayName'       => $row['display_name'],
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),
            'updatedOn'         => $this->filterDbDate($row['update_datetime']),
            'updatedBy'         => $this->filterDbInt($row['update_by']),
            'emailVerified'     => $this->filterDbBool($row['email_verified']),
            'isMultiPersonUser' => $this->filterDbBool($row['multi_person_user']),
            'verificationToken' => $row['verification_token'],
            'verificationExpiration' => $this->filterDbDate($row['verification_expiration']),
            'active'            => $this->filterDbBool($row['state']),
            'personId'          => $this->filterDbId($row['PersID']),
            'roles'             => [],
            'rolesList'         => [],
        ];
        return $processedRow;
    }

    protected function userPostprocessor($data, $newData, $entityAction)
    {
        //if roles is null, we assume the user had no intention of updating roles
        if (isset($data['roles']) || isset($data['rolesList'])) {
            //$newData won't come linked from the caller so link it first
            if (SionTable::ENTITY_ACTION_UPDATE === $entityAction) {
                $this->linkUser($newData);
            } elseif (SionTable::ENTITY_ACTION_CREATE === $entityAction) {
                $data['userId'] = $newData['userId'];
            }
            if (isset($this->logger)) {
                $this->logger->info(
                    'Updating user roles',
                    [
                        'userId'    => $data['userId'],
                        'oldRoles'  => isset($newData['rolesList']) ? $newData['rolesList'] : [],
                        'newRoles'  => isset($data['rolesList']) ? $data['rolesList'] : [],
                    ]
                );
            }
            $this->updateUserRoles($data, $newData); //this function will clear cache
        }
    }

    /**
     * Get user properties
     * @param int|string $id
     * @return mixed[]
     */
    public function getUser($id)
    {
        //see if we can grab the user out of the cache
        $cacheKey = 'all-linked-users';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            if (isset($cache[$id])) {
                return $cache[$id];
            }
        }

        $objects = $this->queryObjects('user', ['userId' => $id]);
        $this->linkUsers($objects);
        if (isset($objects[$id])) {
            return $objects[$id];
        }
        return null;
    }

    /**
     * Hash a plaintext login/verification token for storage and lookup.
     * Only the hash ever touches the database.
     *
     * @param string $plaintextToken
     * @return string 64-character lowercase hex
     */
    public static function hashToken($plaintextToken)
    {
        return hash('sha256', (string) $plaintextToken);
    }

    /**
     * Get user from a plaintext token, including roles
     * @param string $token
     * @return array|null
     */
    public function getUserFromToken($token)
    {
        if (! is_string($token) || '' === $token) {
            return null;
        }
        return $this->getUserFromHashedToken(self::hashToken($token));
    }

    /**
     * Get user from an already hashed token, including roles
     * @param string $hashedToken
     * @return array|null
     */
    public function getUserFromHashedToken($hashedToken)
    {
        if (! is_string($hashedToken) || '' === $hashedToken) {
            return null;
        }
        $results = $this->queryObjects('user', ['verificationToken' => $hashedToken]);
        //it should be exactly 1. If there are duplicate tokens floating, we err on the safe side
        if (1 === count($results)) {
            $this->linkUsers($results);
            $user = current($results);
            return $user;
        }
        return null;
    }

    /**
     * Store a hashed verification token along with its expiration
     * @param int $userId
     * @param string $hashedToken
     * @param \DateTime $expiration
     * @return mixed
     */
    public function setVerificationToken($userId, $hashedToken, \DateTime $expiration)
    {
        return $this->updateEntity('user', $userId, [
            'verificationToken' => $hashedToken,
            'verificationExpiration' => $expiration,
        ]);
    }

    /**
     * Consume the user's verification token after successful redemption:
     * burn it (single use, prevents replay) and mark the email address
     * verified, since redeeming something sent to it proves ownership.
     * @param int $userId
     * @return mixed
     */
    public function clearVerificationToken($userId)
    {
        return $this->updateEntity('user', $userId, [
            'verificationToken' => null,
            'verificationExpiration' => null,
            'emailVerified' => 1,
        ]);
    }

    /**
     * Mark a user as active and their email address as verified.
     *
     * **Nothing in this module calls this any more** (as of 2026-08-21), and that is not an
     * oversight to tidy away: it is kept because it is the one operation an administrator
     * genuinely needs -- undo a deactivation -- and because it is public API of a library
     * two applications vendor. What it must not go back to being is part of the sign-in
     * path: verifyAction() used to call it, which is what stopped `state` from being able
     * to mean "banned". Anything reaching for this to express "the address checked out"
     * wants clearVerificationToken(), which sets email_verified and touches nothing else.
     *
     * @param int $userId
     * @return mixed
     */
    public function activateUser($userId)
    {
        return $this->updateEntity('user', $userId, [
            'active' => 1,
            'emailVerified' => 1,
        ]);
    }

    /**
     * Register a brand new account from nothing but an email address.
     *
     * The account starts out **active and unverified**, and the pairing is the point:
     * the two columns carry two different facts and used to be conflated.
     *
     * `state` (the Active checkbox on /users/{id}/edit) means "may sign in". A new
     * account may, or open registration would not work at all -- the very first magic
     * link would be refused by the deactivation check in
     * JUser\Controller\LoginController.
     *
     * `email_verified` means "someone has proved they read mail at this address", and
     * that is false until the link is redeemed. Redeeming sets it, in
     * clearVerificationToken() as part of burning the token.
     *
     * This created the account with `active => 0` until 2026-08-21, which worked only
     * because verifyAction() then *activated* whatever it redeemed -- and that in turn
     * meant a deactivated account reactivated itself on its next sign-in link, so `state`
     * could not express a ban. See the comment in verifyAction().
     *
     * @param string $email
     * @return array|null the new user row, or null on failure
     */
    public function createUserFromEmail($email)
    {
        $email = trim((string) $email);
        $localPart = strstr($email, '@', true);
        if (false === $localPart || '' === $localPart) {
            $localPart = $email;
        }
        $username = $this->makeUniqueUsername($localPart);
        $displayName = substr($localPart, 0, self::DISPLAY_NAME_MAX_LENGTH);
        if ('' === $displayName) {
            $displayName = $username;
        }

        $defaultRoles = $this->getDefaultRoles();
        $data = [
            'username'      => $username,
            'email'         => $email,
            'displayName'   => $displayName,
            'active'        => 1,
            'emailVerified' => 0,
            'roles'         => $defaultRoles,
            'rolesList'     => array_keys($defaultRoles),
        ];
        if (isset($this->logger)) {
            $this->logger->info("JUser: Registering a new account.", ['email' => $email, 'username' => $username]);
        }
        $newId = $this->createEntity('user', $data);
        if (! $newId) {
            if (isset($this->logger)) {
                $this->logger->error("JUser: Failed registering a new account.", ['email' => $email]);
            }
            return null;
        }
        return $this->getUser($newId);
    }

    /**
     * Build a username from an email local part, suffixing digits until it's free
     * @param string $base
     * @return string
     */
    public function makeUniqueUsername($base)
    {
        $base = preg_replace('/[^0-9A-Za-z\-_.]/', '', (string) $base);
        if ('' === $base) {
            $base = 'user';
        }
        $base = substr($base, 0, self::USERNAME_MAX_LENGTH - 6);
        $candidate = $base;
        $suffix = 1;
        while (null !== $this->findByUsername($candidate)) {
            $candidate = $base . $suffix;
            $suffix++;
            if ($suffix > 99999) {
                //ludicrously unlikely; fall back to something certainly unique
                $candidate = $base . bin2hex(random_bytes(3));
                break;
            }
        }
        return $candidate;
    }

    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     */
    public function deleteUser($id)
    {
        $result = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME)
        ->delete(['user_id' => $id]);
        $result = $this->getTableGateway(self::USER_TABLE_NAME)
        ->delete(['user_id' => $id]);
        $this->removeDependentCacheItems('user');
        return $result;
    }

    /**
     * Gets list of roles
     * @return mixed[]
     */
    public function getRoles()
    {
        $cacheKey = 'role';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $objects = $this->getObjects('user-role');
        $this->linkRoles($objects);

        $this->cacheEntityObjects($cacheKey, $objects, ['user-role']);
        return $objects;
    }

    public function linkRoles(&$objects)
    {
        foreach ($objects as $roleId => $object) {
            if (isset($object['parentId']) && isset($objects[$object['parentId']])) {
                $objects[$roleId]['parentName'] = $objects[$object['parentId']]['name'];
            }
        }
    }

    public function getDefaultRoles()
    {
        $objects = $this->queryObjects('user-role', ['isDefault' => '1']);
        return $objects;
    }

    protected function processRoleRow($row)
    {
        $processedRow = [
            'roleId'            => $this->filterDbId($row['id']),
            'name'              => $row['role_id'],
            'isDefault'         => $this->filterDbBool($row['is_default']),
            'parentId'          => $this->filterDbId($row['parent_id']),
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),

            'parentName'        => null,
        ];
        return $processedRow;
    }

    public function getRolesValueOptions()
    {
        $cacheKey = 'roles-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $roles = $this->getRoles();
        $return = [];
        foreach ($roles as $role) {
            $return[$role['roleId']] = $role['name'] .
               ($role['parentId'] ? ' (child of ' . $role['parentName'] . ')' : '');
        }
        $this->cacheEntityObjects($cacheKey, $return, ['user-role']);
        return $return;
    }

    /**
     * Gets list of user role links
     * @return mixed[]
     */
    protected function getUserRoleLinker($userIds = [])
    {
        $cacheKey = 'user-role-linker';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $gateway = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME);
        $select = $this->getSelectPrototype('user-role-link');
        if (! empty($userIds)) {
            $select->where(['user_id' => $userIds]);
        }
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processUserRoleLinkerRow($row);
            $objects[] = $processedRow;
        }
        $this->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role', 'user-role-link']);
        return $objects;
    }

    protected function processUserRoleLinkerRow($row)
    {
        $processedRow = [
            'linkId'            => $this->filterDbId($row['id']),
            'userId'            => $this->filterDbId($row['user_id']),
            'roleId'            => $this->filterDbId($row['role_id']),
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),

            //from user_role
            'name'              => $row['role_name'],
            'isDefault'         => $this->filterDbBool($row['is_default']),
            'parentId'          => $this->filterDbId($row['parent_id']),
        ];
        return $processedRow;
    }

    /**
     * Check if a user has a certain role
     * @param int $userId
     * @param int $roleId
     * @return bool
     */
    public function userHasRole($userId, $roleId)
    {
        //@todo this could be done with a simple SQL (if it's not called too often)
        $userId = $this->filterDbId($userId);
        $roleId = $this->filterDbId($roleId);
        if (! $userId) {
            throw new \InvalidArgumentException('Invalid user passed.');
        }
        if (! $roleId) {
            throw new \InvalidArgumentException('Invalid role passed.');
        }
        $user = $this->getUser($userId);
        if (! $user) {
            return false;
        }
        if (isset($user['roles'][$roleId])) {
            return true;
        }
        return false;
    }

    /**
     * Take two arrays referring to the same user--an old and updated copy--and update the linked roles
     * associated.
     * @param array $newUser
     * @param array $oldUser
     * @return number
     */
    protected function updateUserRoles(array $newUser, array $oldUser)
    {
        if (
            ! $newUser || ! is_array($newUser) ||
            ! isset($newUser['userId']) ||
            ! isset($newUser['rolesList'])
        ) {
            return 0;
        }
        $newRoles = $newUser['rolesList'];
        if ($oldUser && isset($oldUser['rolesList'])) {
            $oldRoles = $oldUser['rolesList'];
        } else {
            $oldRoles = [];
        }
        $allRoles = $this->getRoles();
        $allRoleIds = array_keys($allRoles);
        $tableGateway = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME);
        $roles = [];
        foreach ($allRoleIds as $roleId) {
            $roles[$roleId] = [
                //don't use strict checks because we can't be sure roleIds will always be the same type
                'old' => in_array($roleId, $oldRoles),
                'new' => in_array($roleId, $newRoles),
            ];
        }
        $return = [];
        foreach ($roles as $roleId => $oldNew) {
            if ($oldNew['new'] === $oldNew['old']) { //if they're the same, we don't need to do anything
                continue;
            }
            $data = ['user_id' => $newUser['userId'], 'role_id' => $roleId];
            if ($oldNew['new'] && ! $oldNew['old']) { //insert a role
                $result = 0;
                $data['create_datetime'] = $this->formatDbDate(new \DateTime(null, new \DateTimeZone('UTC')));
                $actingUserId = $this->getActingUserId();
                if (null !== $actingUserId) {
                    $data['create_by'] = $actingUserId;
                }
                $result = $tableGateway->insert($data);
                $return[] = [
                    'method'    => 'insert',
                    'data'      => $data,
                    'result'    => $result,
                ];
                continue;
            }
            if (! $oldNew['new'] && $oldNew['old']) { //delete a role
                $result = $tableGateway->delete($data);
                $return[] = [
                    'method'    => 'delete',
                    'data'      => $data,
                    'result'    => $result
                ];
                continue;
            }
        }
        if (isset($this->logger)) {
            $this->logger->info("JUser: Updated user roles.", ['result' => $return]);
        }
        /*
         * Even though SionTable::updateEntity should clear cache after an update, this function
         * could be called from somewhere else. Clear the cache just in case
         */
        $this->removeDependentCacheItems('user');

        return count($return);
    }

    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('user' === $entity) {
            $select->order(['username']);
        }
        if ('user-role' === $entity) {
            $select->order(['role_id']);
        }
        if ('user-role-link' === $entity) {
            $select->join(
                'user_role',
                'user_role.id = user_role_linker.role_id',
                ['role_name' => 'role_id', 'is_default', 'parent_id'],
                Select::JOIN_INNER
            );
            $select->order(['user_id', 'role_id']);
        }
        return $select;
    }

    /**
     * @throws \Exception
     * @return \JUser\Service\Mailer
     */
    public function getMailer()
    {
        if (! isset($this->mailer)) {
            throw new \Exception('The mailer is not set.');
        }
        return $this->mailer;
    }

    /**
     * @param Mailer $mailer
     * @return self
     */
    public function setMailer(Mailer $mailer)
    {
        $this->mailer = $mailer;
        return $this;
    }
}
