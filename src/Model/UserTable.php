<?php
namespace JUser\Model;

use Zend\Db\Sql\Select;
use SionModel\Db\Model\SionCacheTrait;
use ZfcUser\Mapper\UserInterface as UserMapperInterface;
use SionModel\Db\Model\SionTable;

class UserTable extends SionTable implements UserMapperInterface
{
    use SionCacheTrait;
    
    const USER_TABLE_NAME = 'user';
    const ROLE_TABLE_NAME = 'user_role';
    const USER_ROLE_LINKER_TABLE_NAME = 'user_role_linker';
    
    /**
     * @param $email
     * @return \ZfcUser\Entity\UserInterface
     */
    public function findByEmail($email)
    {
        if ($this->logger) {
            $this->logger->debug("Looking up user by email", ['email' => $email]);
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
        //@todo trigger find event
        return $userObject;
    }
    
    /**
     * @param string $username
     * @return \ZfcUser\Entity\UserInterface
     */
    public function findByUsername($username)
    {
        if ($this->logger) {
            $this->logger->debug("Looking up user by username", ['username' => $username]);
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
            $this->logger->debug("Looking up user by id", ['id' => $id]);
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
        if (isset($this->logger)) {
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
            $this->logger->info("About to insert a new user.", ['caller' => $caller, 'user' => $user]);
        }
        $result = $this->createEntity('user', $data);
        if (false === $result) {
            if (isset($this->logger)) {
                $this->logger->err("Failed inserting a new user.", ['result' => $result, 'user' => $user]);
            }
            throw new \Exception('Error inserting a new user.');
        } else {
            if (isset($this->logger)) {
                $this->logger->info("Finished inserting a new user.", ['result' => $result]);
            }
        }
        return $result;
    }
    
    /**
     * @param \ZfcUser\Entity\UserInterface $user
     */
    public function updateUser(\ZfcUser\Entity\UserInterface $user)
    {
        
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
     * Add role data to an array of user arrays
     * @param array $users
     */
    public function linkUsers(array &$users)
    {
        if (empty($users)) {
            return;
        }
        //first compile list of user id to get just the rows we need
        $userIds = array_keys($users);
        $roleLinks = $this->queryObjects('user-role-link', ['userId' => $userIds]);
        foreach ($roleLinks as $key => $link) {
            if (isset($users[$link['userId']])) {
                $users[$link['userId']]['roles'][$key] = $roleLinks[$key];
            }
        }
        
        foreach ($userIds as $id) {
            $users[$id]['rolesList'] = array_keys($users[$id]['roles']);
        }
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
//            'languages'         => $this->filterDbArray($row['lang'], ';'),
            'personId'          => $this->filterDbId($row['PersID']),
            'roles'             => [],
            'rolesList'         => [],
        ];
        return $processedRow;
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
        $results = $this->queryObjects('user', ['verification_token' => $token]);
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
//         $gateway = $this->getTableGateway(self::ROLE_TABLE_NAME);
//         $select = $this->getSelectPrototype('user-role');
//         $results = $gateway->selectWith($select);
//         //manipulate column names
//         $objects = [];
//         foreach ($results as $row) {
//             $processedRow = $this->processRoleRow($row);
//             $id = $processedRow['roleId'];
//             $objects[$id] = $processedRow;
//         }
//         foreach ($objects as $roleId => $object) {
//             if (isset($object['parentId']) && isset($objects[$object['parentId']])) {
//                 $objects[$roleId]['parentName'] = $objects[$object['parentId']]['name'];
//             }
//         }
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
        $gateway = $this->getTableGateway(self::ROLE_TABLE_NAME);
        $select = $this->getSelectPrototype('user-role');
        $select->where(['is_default' => '1']);
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processRoleRow($row);
            $id = $processedRow['roleId'];
            $objects[$id] = $processedRow;
        }
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
                if (is_object($this->actingUser)) {
                    $data['create_by'] = $this->actingUser->id;
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
    
    //     public function createRole($data)
    //     {
    //         $tableName     = self::ROLE_TABLE_NAME;
    //         $tableGateway  = $this->getTableGateway($tableName);
    //         $requiredCols  = array(
    //             'name'
    //         );
    //         $updateCols = [
        //             'name'      => 'role_id',
    //             'parentId'  => 'parent_id',
    //             'isDefault' => 'is_default',
    //             'createdOn' => 'create_datetime',
    //             'createdBy' => 'create_by',
    //         ];
    //         $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway);
    //         $this->removeDependentCacheItems('role');
    //         return $return;
    //     }
    
    /**
     *
     * @param string[][] $data
     */
    //     public function createUser($data)
    //     {
    //         $tableName     = self::USER_TABLE_NAME;
    //         $tableGateway  = $this->getTableGateway($tableName);
    //         $requiredCols  = [
    //             'username', 'email', 'displayName', 'password'
    //         ];
    //         $updateCols = [
        //             'userId'       => 'user_id',
    //             'username'     => 'username',
    //             'email'        => 'email',
    //             'displayName'  => 'display_name',
    //             'password'     => 'password',
    //             'createdOn'    => 'create_datetime',
    //             'createdBy'    => 'create_by',
    //             'updatedOn'    => 'update_datetime',
    //             'updatedBy'    => 'update_by',
    //             'emailVerified'=> 'email_verified',
    //             'mustChangePassword'=> 'must_change_password',
    //             'isMultiPersonUser' => 'multi_person_user',
    //             'verificationToken' => 'verification_token',
    //             'verificationExpiration' => 'verification_expiration',
    //             'active'       => 'state',
    //             //'languages'    => 'lang',
    //             'personId'     => 'PersID',
    //         ];
    //         $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway);
    //         $data['userId'] = $return;
    //         $this->updateUserRoles($data, null);
    //         $this->removeDependentCacheItems('user');
    //         return $return;
    //     }
    
    /**
     *
     * @param int|string $id
     * @param string[][] $data
     */
    //     public function updateUser($id, $data)
    //     {
    //         $tableName     = self::USER_TABLE_NAME;
    //         $tableKey      = 'user_id';
    //         $tableGateway  = $this->getTableGateway($tableName);
    
    //         if (!is_numeric($id)) {
    //             throw new \InvalidArgumentException('Invalid user id provided.');
    //         }
    //         $user = $this->getUser($id);
    //         if (!$user) {
    //             throw new \InvalidArgumentException('No user provided.');
    //         }
    //         $updateCols = array(
    //             'userId'       => 'user_id',
    //             'username'     => 'username',
    //             'email'        => 'email',
    //             'displayName'  => 'display_name',
    // //            'password'     => 'password', whenever we update the password we don't use this function
    //             'createdOn'    => 'create_datetime',
    //             'createdBy'    => 'create_by',
    //             'updatedOn'    => 'update_datetime',
    //             'updatedBy'    => 'update_by',
    //             'emailVerified'=> 'email_verified',
    //             'mustChangePassword'=> 'must_change_password',
    //             'isMultiPersonUser' => 'multi_person_user',
    //             'verificationToken' => 'verification_token',
    //             'verificationExpiration' => 'verification_expiration',
    //             'active'       => 'state',
    //             //'languages'    => 'lang',
    //             'personId'     => 'PersID',
    //         );
    //         $return = $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $user);
    //         $this->updateUserRoles($data, $user);
    //         $this->removeDependentCacheItems('user');
    //         return $return;
    //     }
    
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
//     protected function getUsersSelectPrototype()
//     {
//         static $select;
//         if (!isset($select)) {
//             $select = new Select(self::USER_TABLE_NAME);
//             $select->columns(['user_id', 'username', 'email', 'display_name', 'password',
//                 'create_datetime', 'update_datetime', 'state', 'lang', 'email_verified',
//                 'must_change_password', 'multi_person_user', 'PersID', 'verification_token',
//                 'verification_expiration', 'create_by', 'update_by']);
//             $select->order(['username']);
//         }

//         return clone $select;
//     }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
//     protected function getRolesSelectPrototype()
//     {
//         static $select;
//         if (!isset($select)) {
//             $select = new Select(self::ROLE_TABLE_NAME);
//             $select->columns(['id', 'role_id', 'is_default', 'parent_id', 'create_by', 'create_datetime']);
//             $select->order(['role_id']);
//         }

//         return clone $select;
//     }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
//     protected function getUserRoleLinkerSelectPrototype()
//     {
//         static $select;
//         if (!isset($select)) {
//             $select = new Select(self::USER_ROLE_LINKER_TABLE_NAME);
//             $select->columns(['user_id', 'role_id', 'create_by', 'create_datetime']);
//             $select->order(['user_id', 'role_id']);
//         }

//         return clone $select;
//     }

//     protected function createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway)
//     {
//         //make sure required cols are being passed
//         foreach ($requiredCols as $colName) {
//             if (!isset($data[$colName])) {
//                 return false;
//             }
//         }

//         $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
//         $updateVals = array();
//         foreach ($data as $col => $value) {
//             if (!key_exists($col, $updateCols)) {
//                 continue;
//             }
//             if ($data[$col] instanceof \DateTime) {
//                 $data[$col] = $data[$col]->format('Y-m-d H:i:s');
//             }
//             $updateVals[$updateCols[$col]] = $data[$col];
//             //check if this column has updatedOn column
//             if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) {
//                 $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
//             }
//             if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
//                 is_object($this->actingUser)) { //check if this column has updatedOn column
//                     $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUser->id;
//             }
//         }
//         if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
//             $updateVals[$updateCols['updatedOn']] = $now;
//         }
//         if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
//                 is_object($this->actingUser)) {
//             $updateVals[$updateCols['updatedBy']] = $this->actingUser->id;
//         }
//         //check if this column has updatedOn column
//         if (key_exists('createdOn', $updateCols) && !key_exists('createdOn', $data)) {
//             $updateVals[$updateCols['createdOn']] = $now;
//         }
//         if (key_exists('createdBy', $updateCols) && !key_exists('createdBy', $data) &&
//             is_object($this->actingUser)) { //check if this column has updatedOn column
//                 $updateVals[$updateCols['createdBy']] = $this->actingUser->id;
//         }
//         if (count($updateVals) > 0) {
//             $tableGateway->insert($updateVals);
//             $newId = $tableGateway->getLastInsertValue();
//             return $newId;
//         }
//         return false;
//     }

//     protected function updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $referenceEntity)
//     {
//         if (is_null($tableName) || $tableName == '') {
//             throw new \Exception('No table name provided.');
//         }
//         if (is_null($tableKey) || $tableKey == '') {
//             throw new \Exception('No table key provided');
//         }
//         $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
//         $updateVals = array();
//         foreach ($referenceEntity as $col => $value) {
//             if (!key_exists($col, $updateCols) || !key_exists($col, $data) || $value == $data[$col]) {
//                 continue;
//             }
//             if ($data[$col] instanceof \DateTime) {
//                 $data[$col] = $data[$col]->format('Y-m-d H:i:s');
//             }
//             $updateVals[$updateCols[$col]] = $data[$col];
//             //check if this column has updatedOn column
//             if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) {
//                 $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
//             }
//             if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
//                 is_object($this->actingUser)) { //check if this column has updatedOn column
//                     $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUser->id;
//             }
//         }
//         if (count($updateVals) > 0) {
//             if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
//                 $updateVals[$updateCols['updatedOn']] = $now;
//             }
//             if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
//                 is_object($this->actingUser)) {
//                     $updateVals[$updateCols['updatedBy']] = $this->actingUser->id;
//             }
//             $result = $tableGateway->update($updateVals, array($tableKey => $id));
//             return $result;
//         }
//         return true;
//     }

    /**
     * @param Where|\Closure|string|array $where
     * @param null|string
     * @param null|array
     * @return array
     */
//     public function fetchSome($where, $sql = null, $sqlArgs = null)
//     {
//         if (null === $where && null === $sql) {
//             throw new \InvalidArgumentException('No query requested.');
//         }
//         if (null !== $sql) {
//             if (null === $sqlArgs) {
//                 $sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes
//             }
//             $result = $this->tableGateway->getAdapter()->query($sql, $sqlArgs);
//         } else {
//             $result = $this->tableGateway->select($where);
//         }

//         $return = [];
//         foreach ($result as $row) {
//             $return[] = $row;
//         }
//         return $return;
//     }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
//     protected function filterDbId($str)
//     {
//         if (null === $str || $str === '' || $str == '0') {
//             return null;
//         }
//         return (int) $str;
//     }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
//     protected function filterDbInt($str)
//     {
//         if (null === $str || $str === '') {
//             return null;
//         }
//         return (int) $str;
//     }

    /**
     * Filter a database boolean
     * @param string $str
     * @return boolean
     */
//     protected function filterDbBool($str)
//     {
//         if (null === $str || $str === '' || $str == '0') {
//             return false;
//         }
//         static $filter;
//         if (!is_object($filter)) {
//             $filter = new Boolean();
//         }
//         return $filter->filter($str);
//     }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
//     protected function filterDbDate($str)
//     {
//         static $tz;
//         if (!isset($tz)) {
//             $tz = new \DateTimeZone('UTC');
//         }
//         if (null === $str || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
//             return null;
//         }
//         try {
//             $return = new \DateTime($str, $tz);
//         } catch (\Exception $e) {
//             $return = null;
//         }
//         return $return;
//     }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
//     protected function filterDbArray($str, $delimiter = '|', $trim = true)
//     {
//         if (!isset($str) || $str == '') {
//             return [];
//         }
//         $return = explode($delimiter, $str);
//         if ($trim) {
//             foreach ($return as $value) {
//                 $value = trim($value);
//             }
//         }
//         return $return;
//     }

    /**
     *
     * @param \DateTime $object
     * @return string
     */
//     protected function formatDbDate($object)
//     {
//         if (!$object instanceof \DateTime) {
//             return $object;
//         }
//         return $object->format('Y-m-d H:i:s');
//     }

//     protected function formatDbArray($arr, $delimiter = '|', $trim = true)
//     {
//         if (!is_array($arr)) {
//             return $arr;
//         }
//         if (empty($arr)) {
//             return null;
//         }
//         if ($trim) {
//             foreach ($arr as $value) {
//                 $value = trim($value);
//             }
//         }
//         $return = implode($delimiter, $arr);
//         return $return;
//     }

//     protected function keyArray(array $a, $key, $unique = true)
//     {
//         $return = array();
//         foreach ($a as $item) {
//             if (!$unique) {
//                 if (isset($return[$item[$key]])) {
//                     $return[$item[$key]][] = $item;
//                 } else {
//                     $return[$item[$key]] = array($item);
//                 }
//             } else {
//                 $return[$item[$key]] = $item;
//             }
//         }
//         return $return;
//     }

    /**
     * Get an instance of a TableGateway for a particular table name
     * @param string $tableName
     */
//     protected function getTableGateway($tableName)
//     {
//         if (key_exists($tableName, $this->tableGatewaysCache)) {
//             return $this->tableGatewaysCache[$tableName];
//         }
//         $gateway = new TableGateway($tableName, $this->adapter);
//         //@todo is there a way to make sure the table exists?
//         return $this->tableGatewaysCache[$tableName] = $gateway;
//     }
}
