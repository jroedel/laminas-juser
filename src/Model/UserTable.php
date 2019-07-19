<?php
namespace JUser\Model;

use Zend\Db\Sql\Select;
use ZfcUser\Mapper\UserInterface as UserMapperInterface;
use SionModel\Db\Model\SionTable;
use JUser\Service\Mailer;

class UserTable extends SionTable implements UserMapperInterface
{
    const USER_TABLE_NAME = 'user';
    const ROLE_TABLE_NAME = 'user_role';
    const USER_ROLE_LINKER_TABLE_NAME = 'user_role_linker';
    
    /** @var Mailer $mailer */
    protected $mailer;
    
    protected $flashMessenger;
    
    /**
     * @param $email
     * @return \ZfcUser\Entity\UserInterface
     */
    public function findByEmail($email)
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if ($this->logger) {
            $this->logger->debug("JUser: Looking up user by email", ['email' => $email, 'caller' => $caller]);
        }
        $results = $this->queryObjects('user', ['email' => $email]);
        if (!isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }
        
        //if we've got an inactive user, notify the user to look for a verification email
        if ('authenticate' === $caller && !$userArray['active'] && !$userArray['emailVerified']) {
            $this->getMailer()->onInactiveUser($userObject, $this);
        }
        
        //@todo trigger find event
        return $userObject;
    }
    
    /**
     * @param string $username
     * @return \ZfcUser\Entity\UserInterface
     */
    public function findByUsername($username)
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if ($this->logger) {
            $this->logger->debug("JUser: Looking up user by username", ['username' => $username, 'caller' => $caller]);
        }
        $results = $this->queryObjects('user', ['username' => $username]);
        if (!isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }
        
        //notify the user to look for a verification email
        if ('authenticate' === $caller && !$userArray['active'] && !$userArray['emailVerified']) {
            $this->getMailer()->onInactiveUser($userObject, $this);
        }
        
        //@todo trigger find event
        return $userObject;
    }
    
    /**
     * @param string|int $id
     * @return \ZfcUser\Entity\UserInterface
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
     * @param \ZfcUser\Entity\UserInterface $user
     */
    public function insertUser(\ZfcUser\Entity\UserInterface $user)
    {
        //figure out what the calling function is. If a user is registering, trigger the email here
        $data = $user->getArrayCopy();
        unset($data['userId']); //we don't have a userId yet
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if (isset($this->logger)) {
            $this->logger->info("JUser: About to insert a new user.", ['caller' => $caller, 'email' => $user->getEmail()]);
        }
        if ('register' === $caller && !isset($data['roles']) || empty($data['roles'])) {
            $defaultRoles = $this->getDefaultRoles();
            $data['roles'] = $defaultRoles;
            $data['rolesList'] = array_keys($defaultRoles);
        }
        $result = $this->createEntity('user', $data);
        if (false === $result) {
            if (isset($this->logger)) {
                $this->logger->err("JUser: Failed inserting a new user.", ['result' => $result, 'user' => $user]);
            }
            throw new \Exception('Error inserting a new user.');
        } else {
            if (isset($this->logger)) {
                $this->logger->info("JUser: Finished inserting a new user.", ['result' => $result]);
            }
            if ('register' === $caller) {
                //we need to send the registration email
                try {
                    //@todo this could be better to schedule with cron, to avoid making the user wait for the send
                    $this->getMailer()->onRegister($user);
                } catch (\Exception $e) {
                    if (isset($this->logger)) {
                        $this->logger->err("JUser: Exception thrown while triggering verification email.", ['exception' => $e]);
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * @param \ZfcUser\Entity\UserInterface $user
     */
    public function updateUser(\ZfcUser\Entity\UserInterface $user)
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
                $this->logger->err("Failed updating a user.", ['result' => $result, 'user' => $user]);
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
        if (!empty($ids)) {
            if (1 === count($ids)) {
                $query['userId'] = current($ids);
            } else {
                $query['userId'] = $ids;
            }
        }
        $objects = $this->queryObjects('user', $query);
        $this->linkUsers($objects);
        
        if (isset($cacheKey)) {
            $this->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role']);
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
            //if we're looking at several users, just get all records, easier to cache
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
        if (!empty($ids)) {
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
            'password'          => $row['password'],
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),
            'updatedOn'         => $this->filterDbDate($row['update_datetime']),
            'updatedBy'         => $this->filterDbInt($row['update_by']),
            'emailVerified'     => $this->filterDbBool($row['email_verified']),
            'mustChangePassword'=> $this->filterDbBool($row['must_change_password']),
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
        if (isset($data['roles'])) { //if roles is null, we assume the user had no intention of updating roles
            //$newData won't come linked from the caller so link it first
            if (SionTable::ENTITY_ACTION_UPDATE === $entityAction) {
                $this->linkUser($newData);
            } elseif (SionTable::ENTITY_ACTION_CREATE === $entityAction) {
                $data['userId'] = $newData['userId'];
            }
            $this->updateUserRoles($data, $newData);
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
     * Get user from token, including roles
     * @param int|string $id
     */
    public function getUserFromToken($token)
    {
        $results = $this->queryObjects('user', ['verificationToken' => $token]);
        //it should be exactly 1. If there are duplicate tokens floating, we err on the safe side
        if (1 === count($results)) {
            $this->linkUsers($results);
            $user = current($results);
            return $user;
        }
        return null;
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
            $return[$role['roleId']] = $role['name'].
               ($role['parentId'] ? ' (child of '.$role['parentName'].')' : '');
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
        if (!empty($userIds)) {
            $select->where(['user_id' => $userIds]);
        }
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processUserRoleLinkerRow($row);
            $objects[] = $processedRow;
        }
        $this->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role']);
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
        if (!$userId) {
            throw new \InvalidArgumentException('Invalid user passed.');
        }
        if (!$roleId) {
            throw new \InvalidArgumentException('Invalid role passed.');
        }
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        if (isset($user['roles'][$roleId])) {
            return true;
        }
        return false;
    }

    protected function updateUserRoles($newUser, $oldUser)
    {
        if (!$newUser || !is_array($newUser) ||
            !isset($newUser['userId']) ||
            !isset($newUser['rolesList'])) {
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
            if ($oldNew['new'] && !$oldNew['old']) { //insert a role
                $result = 0;
                $data['create_datetime'] = $this->formatDbDate(new \DateTime(null, new \DateTimeZone('UTC')));
                if (isset($this->actingUserId)) {
                    $data['create_by'] = $this->actingUserId;
                }
                $result = $tableGateway->insert($data);
                $return[] = [
                    'method'    => 'insert',
                    'data'      => $data,
                    'result'    => $result,
                ];
                continue;
            }
            if (!$oldNew['new'] && $oldNew['old']) { //delete a role
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
            $this->logger->debug("JUser: Updated user roles.", ['result' => $return]);
        }
        $this->removeDependentCacheItems('user');
        return count($return);
    }

    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     * @param string $newPass
     */
    public function updateUserPassword($id, $newPass)
    {
        $result = $this->getTableGateway(self::USER_TABLE_NAME)
            ->update(['password' => $newPass], ['user_id' => $id]);
        $this->removeDependentCacheItems('user');
        return $result;
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
        if (!isset($this->mailer)) {
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
