<?php
namespace JUser\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Filter\Boolean;
use Zend\Cache\Storage\StorageInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;

class UserTable
{
    const USER_TABLE_NAME = 'user';
    const ROLE_TABLE_NAME = 'user_role';
    const USER_ROLE_LINKER_TABLE_NAME = 'user_role_linker';

    /**
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     *
     * @var array $tableGatewaysCache
     */
    protected $tableGatewaysCache = [];

    /**
     *
     * @var User
     */
    protected $actingUser;

    /**
     * @var StorageInterface $cache
     */
    protected $persistentCache;

    /**
     * @var int $maxItemsToCache
     */
    protected $maxItemsToCache = 2;

    /**
     * For each cache key, the list of entities they depend on.
     * For example:
     * [
     *      'events' => ['event', 'dates',  'emails', 'persons'],
     *      'unlinked-events => ['event'],
     * ]
     * That is to say, each time an entity of that type is created or updated,
     * the cache will be invalidated.
     * @var array
     */
    protected $cacheDependencies = [];

    /**
     * List of keys that should be persisted onFinish
     * @var array
     */
    protected $newPersistentCacheItems = [];

    /**
     * @var mixed[] $memoryCache
     */
    protected $memoryCache = [];

    /**
     * @param TableGateway $userGateway
     * @param TableGateway $userRoleGateway
     * @param \JUser\Entity\User
     */
    public function __construct(AdapterInterface $adapter, User $actingUser = null)
    {
        $this->adapter = $adapter;
        $this->actingUser = $actingUser;
    }

    /**
     * Gets list of users
     *
     * @return mixed[]
     */
    public function getUsers(array $ids = [])
    {
        $cacheKey = 'users';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $gateway = $this->getTableGateway(self::USER_TABLE_NAME);
        $select = $this->getUsersSelectPrototype();
        if (!empty($ids)) {
            $select->where(['user_id' => $ids]);
        }
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processUserRow($row);
            $id = $processedRow['userId'];
            $objects[$id] = $processedRow;
        }
        $links = $this->getUserRoleLinker();
        $roles = $this->getRoles();
        foreach ($links as $link) {
            if (isset($objects[$link['userId']]) && isset($roles[$link['roleId']])) {
                $objects[$link['userId']]['roles'][$link['roleId']] = $roles[$link['roleId']];
            }
        }
        foreach ($objects as $id => $object) {
            $objects[$id]['rolesList'] = array_keys($objects[$id]['roles']);
        }

        $this->cacheEntityObjects($cacheKey, $objects, ['user']);
        return $objects;
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
     */
    public function getUser($id)
    {
        //this function gets called a lot so let's make this efficient
        if (isset($this->memoryCache['users']) && isset($this->memoryCache['users'][$id])) {
            return $this->memoryCache['users'][$id];
        }
        $results = $this->getUsers();
        //manipulate column names
        if (isset($results) && isset($results[$id])) {
            return $results[$id];
        } else {
            return null;
        }
    }

    /**
     * Get user from token, doesn't include roles
     * @param int|string $id
     */
    public function getUserFromToken($token)
    {
        $gateway = $this->getTableGateway(self::USER_TABLE_NAME);
        $select = $this->getUsersSelectPrototype();
            $select->where(['verification_token' => $token]);
        $results = $gateway->selectWith($select);
        //manipulate column names
        if (0 !== $results->count()) {
            $row = $results->current();
            //@todo make this more efficient, but we need roles too
            return $this->getUser($row['user_id']);
        }
        return null;
    }

    /**
     *
     * @param string[][] $data
     */
    public function createUser($data)
    {
        $tableName     = self::USER_TABLE_NAME;
        $tableGateway  = $this->getTableGateway($tableName);
        $requiredCols  = [
            'username', 'email', 'displayName', 'password'
        ];
        $updateCols = [
            'userId'       => 'user_id',
            'username'     => 'username',
            'email'        => 'email',
            'displayName'  => 'display_name',
            'password'     => 'password',
            'createdOn'    => 'create_datetime',
            'createdBy'    => 'create_by',
            'updatedOn'    => 'update_datetime',
            'updatedBy'    => 'update_by',
            'emailVerified'=> 'email_verified',
            'mustChangePassword'=> 'must_change_password',
            'isMultiPersonUser' => 'multi_person_user',
            'verificationToken' => 'verification_token',
            'verificationExpiration' => 'verification_expiration',
            'active'       => 'state',
            //'languages'    => 'lang',
            'personId'     => 'PersID',
        ];
        $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway);
        $data['userId'] = $return;
        $this->updateUserRoles($data, null);
        $this->removeDependentCacheItems('user');
        return $return;
    }

    /**
     *
     * @param int|string $id
     * @param string[][] $data
     */
    public function updateUser($id, $data)
    {
        $tableName     = self::USER_TABLE_NAME;
        $tableKey      = 'user_id';
        $tableGateway  = $this->getTableGateway($tableName);

        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid user id provided.');
        }
        $user = $this->getUser($id);
        if (!$user) {
            throw new \InvalidArgumentException('No user provided.');
        }
        $updateCols = array(
            'userId'       => 'user_id',
            'username'     => 'username',
            'email'        => 'email',
            'displayName'  => 'display_name',
//            'password'     => 'password', whenever we update the password we don't use this function
            'createdOn'    => 'create_datetime',
            'createdBy'    => 'create_by',
            'updatedOn'    => 'update_datetime',
            'updatedBy'    => 'update_by',
            'emailVerified'=> 'email_verified',
            'mustChangePassword'=> 'must_change_password',
            'isMultiPersonUser' => 'multi_person_user',
            'verificationToken' => 'verification_token',
            'verificationExpiration' => 'verification_expiration',
            'active'       => 'state',
            //'languages'    => 'lang',
            'personId'     => 'PersID',
        );
        $return = $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $user);
        $this->updateUserRoles($data, $user);
        $this->removeDependentCacheItems('user');
        return $return;
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
        $gateway = $this->getTableGateway(self::ROLE_TABLE_NAME);
        $select = $this->getRolesSelectPrototype();
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processRoleRow($row);
            $id = $processedRow['roleId'];
            $objects[$id] = $processedRow;
        }
        foreach ($objects as $roleId => $object) {
            if (isset($object['parentId']) && isset($objects[$object['parentId']])) {
                $objects[$roleId]['parentName'] = $objects[$object['parentId']]['name'];
            }
        }

        $this->cacheEntityObjects($cacheKey, $objects, ['role']);
        return $objects;
    }

    protected function processRoleRow($row)
    {
        $processedRow = [
            'roleId'            => $this->filterDbId($row['id']),
            'name'              => $row['role_id'],
            //'isDefault'         => $this->filterDbBool($row['is_default']),
            'parentId'          => $this->filterDbId($row['parent_id']),
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),

            'parentName'        => null,
        ];
        return $processedRow;
    }

    public function createRole($data)
    {
        $tableName     = self::ROLE_TABLE_NAME;
        $tableGateway  = $this->getTableGateway($tableName);
        $requiredCols  = array(
            'name'
        );
        $updateCols = [
            'name'       => 'role_id',
            'parentId'     => 'parent_id',
            //'isDefault'     => 'is_default',
             'createdOn'    => 'create_datetime',
             'createdBy'    => 'create_by',
        ];
        $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway);
        $this->removeDependentCacheItems('role');
        return $return;
    }

    public function getRolesValueOptions()
    {
        $roles = $this->getRoles();
        $return = [];
        foreach ($roles as $role) {
            $return[$role['roleId']] = $role['name'].
               ($role['parentId'] ? ' (child of '.$role['parentName'].')' : '');
        }
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
        $select = $this->getUserRoleLinkerSelectPrototype();
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
        $this->cacheEntityObjects($cacheKey, $objects, ['user', 'role']);
        return $objects;
    }

    protected function processUserRoleLinkerRow($row)
    {
        $processedRow = [
            'userId'            => $this->filterDbId($row['user_id']),
            'roleId'            => $this->filterDbId($row['role_id']),
            'createdOn'         => $this->filterDbDate($row['create_datetime']),
            'createdBy'         => $this->filterDbInt($row['create_by']),
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
        $tableGateway = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME);
        $roles = [];
        foreach ($allRoles as $roleId => $roleObject) {
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

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getUsersSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select(self::USER_TABLE_NAME);
            $select->columns(['user_id', 'username', 'email', 'display_name', 'password',
                'create_datetime', 'update_datetime', 'state', 'lang', 'email_verified',
                'must_change_password', 'multi_person_user', 'PersID', 'verification_token',
                'verification_expiration', 'create_by', 'update_by']);
            $select->order(['username']);
        }

        return clone $select;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getRolesSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select(self::ROLE_TABLE_NAME);
            $select->columns(['id', 'role_id', 'is_default', 'parent_id', 'create_by', 'create_datetime']);
            $select->order(['role_id']);
        }

        return clone $select;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getUserRoleLinkerSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select(self::USER_ROLE_LINKER_TABLE_NAME);
            $select->columns(['user_id', 'role_id', 'create_by', 'create_datetime']);
            $select->order(['user_id', 'role_id']);
        }

        return clone $select;
    }

    protected function createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway)
    {
        //make sure required cols are being passed
        foreach ($requiredCols as $colName) {
            if (!isset($data[$colName])) {
                return false;
            }
        }

        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = array();
        foreach ($data as $col => $value) {
            if (!key_exists($col, $updateCols)) {
                continue;
            }
            if ($data[$col] instanceof \DateTime) {
                $data[$col] = $data[$col]->format('Y-m-d H:i:s');
            }
            $updateVals[$updateCols[$col]] = $data[$col];
            //check if this column has updatedOn column
            if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) {
                $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
            }
            if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
                is_object($this->actingUser)) { //check if this column has updatedOn column
                    $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUser->id;
            }
        }
        if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
            $updateVals[$updateCols['updatedOn']] = $now;
        }
        if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
                is_object($this->actingUser)) {
            $updateVals[$updateCols['updatedBy']] = $this->actingUser->id;
        }
        //check if this column has updatedOn column
        if (key_exists('createdOn', $updateCols) && !key_exists('createdOn', $data)) {
            $updateVals[$updateCols['createdOn']] = $now;
        }
        if (key_exists('createdBy', $updateCols) && !key_exists('createdBy', $data) &&
            is_object($this->actingUser)) { //check if this column has updatedOn column
                $updateVals[$updateCols['createdBy']] = $this->actingUser->id;
        }
        if (count($updateVals) > 0) {
            $resultsInsert = $tableGateway->insert($updateVals);
            $newId = $tableGateway->getLastInsertValue();
            $changeVals = array(array(
                'table'    => $tableName,
                'column'   => 'newEntry',
                'id'       => $newId
            ));
//             $this->reportChange($changeVals);
            return $newId;
        }
        return false;
    }

    protected function updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $referenceEntity)
    {
        if (is_null($tableName) || $tableName == '') {
            throw new \Exception('No table name provided.');
        }
        if (is_null($tableKey) || $tableKey == '') {
            throw new \Exception('No table key provided');
        }
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = array();
        $changes = array();
        foreach ($referenceEntity as $col => $value) {
            if (!key_exists($col, $updateCols) || !key_exists($col, $data) || $value == $data[$col]) {
                continue;
            }
            if ($data[$col] instanceof \DateTime) {
                $data[$col] = $data[$col]->format('Y-m-d H:i:s');
            }
            $updateVals[$updateCols[$col]] = $data[$col];
            //check if this column has updatedOn column
            if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) {
                $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
            }
            if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
                is_object($this->actingUser)) { //check if this column has updatedOn column
                    $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUser->id;
            }
            $changes[] = array(
                'table'    => $tableName,
                'column'   => $col,
                'id'       => $id,
                'oldValue' => $value,
                'newValue' => $data[$col],
            );
        }
        if (count($updateVals) > 0) {
            if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
                $updateVals[$updateCols['updatedOn']] = $now;
            }
            if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
                is_object($this->actingUser)) {
                    $updateVals[$updateCols['updatedBy']] = $this->actingUser->id;
            }
                $result = $tableGateway->update($updateVals, array($tableKey => $id));
                //             $this->reportChange($changes);
                return $result;
        }
        return true;
    }

    /**
     * @param Where|\Closure|string|array $where
     * @param null|string
     * @param null|array
     * @return array
     */
    public function fetchSome($where, $sql = null, $sqlArgs = null)
    {
        if (null === $where && null === $sql) {
            throw new \InvalidArgumentException('No query requested.');
        }
        if (null !== $sql) {
            if (null === $sqlArgs) {
                $sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes
            }
            $result = $this->tableGateway->getAdapter()->query($sql, $sqlArgs);
        } else {
            $result = $this->tableGateway->select($where);
        }

        $return = [];
        foreach ($result as $row) {
            $return[] = $row;
        }
        return $return;
    }

    /**
     * Cache some entities. A simple proxy of the cache's setItem method with dependency support.
     *
     * @param string $cacheKey
     * @param mixed[] $objects
     * @param array $entityDependencies
     * @return boolean
     */
    public function cacheEntityObjects($cacheKey, &$objects, array $cacheDependencies = [])
    {
        if (!isset($this->persistentCache)) {
            throw new \Exception('The cache must be configured to cache entites.');
        }
        //$cacheKey = $this->getSionTableIdentifier().'-'.$cacheKey;
        $this->memoryCache[$cacheKey] = $objects;
        $this->newPersistentCacheItems[] = $cacheKey;
        $this->cacheDependencies[$cacheKey] = $cacheDependencies;
        return true;
    }

    /**
     * Retrieve a cache item. A simple proxy of the cache's getItem method.
     * First we check the memoryCache, if it's not there, we look in the
     * persistent cache. If it's in the persistent cache, we set it in the
     * memory cache and return the objects. If we don't find the key we
     * return null.
     * @param string $key
     * @param bool $success
     * @param mixed $casToken
     * @throws \Exception
     * @return mixed|null
     */
    public function &fetchCachedEntityObjects($key, &$success = null, $casToken = null)
    {
        if (null === $this->persistentCache) {
            throw new \Exception('Please set a cache before fetching cached entities.');
        }
        //$key = $this->getSionTableIdentifier().'-'.$key;
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        $objects = $this->persistentCache->getItem($key, $success, $casToken);
        if ($success) {
            return $this->memoryCache[$key];
        }
        $null = null;
        return $null;
    }

    /**
     * Examine the $this->cacheDependencies array to see if any depends on the entity passed.
     * @param string $entity
     * @return bool
     */
    public function removeDependentCacheItems($entity)
    {
        $cache = $this->getPersistentCache();
        foreach ($this->cacheDependencies as $key => $dependentEntities) {
            if (in_array($entity, $dependentEntities) || $key == 'changes' || $key == 'problems') {
                if (is_object($cache)) {
                    $cache->removeItem($key);
                }
                if (isset($this->memoryCache[$key])) {
                    unset($this->memoryCache[$key]);
                }
            }
        }

        return true;
    }

    /**
     * At the end of the page load, cache any uncached items up to max_number_of_items_to_cache.
     * This is because serializing big objects can be very memory expensive.
     */
    public function onFinish()
    {
        $maxObjects = $this->getMaxItemsToCache();
        $count = 0;
        if (is_object($this->persistentCache)) {
            $this->persistentCache->setItem('cachedependencies', $this->cacheDependencies);
            foreach ($this->newPersistentCacheItems as $key) {
                if (key_exists($key, $this->memoryCache)) {
                    $this->persistentCache->setItem($key, $this->memoryCache[$key]);
                    $count++;
                }
                if ($count >= $maxObjects) {
                    break;
                }
            }
        }
    }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
    protected function filterDbId($str)
    {
        if (null === $str || $str === '' || $str == '0') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
    protected function filterDbInt($str)
    {
        if (null === $str || $str === '') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Filter a database boolean
     * @param string $str
     * @return boolean
     */
    protected function filterDbBool($str)
    {
        if (null === $str || $str === '' || $str == '0') {
            return false;
        }
        static $filter;
        if (!is_object($filter)) {
            $filter = new Boolean();
        }
        return $filter->filter($str);
    }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
    protected function filterDbDate($str)
    {
        static $tz;
        if (!isset($tz)) {
            $tz = new \DateTimeZone('UTC');
        }
        if (null === $str || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
            return null;
        }
        try {
            $return = new \DateTime($str, $tz);
        } catch (\Exception $e) {
            $return = null;
        }
        return $return;
    }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
    protected function filterDbArray($str, $delimiter = '|', $trim = true)
    {
        if (!isset($str) || $str == '') {
            return [];
        }
        $return = explode($delimiter, $str);
        if ($trim) {
            foreach ($return as $value) {
                $value = trim($value);
            }
        }
        return $return;
    }

    /**
     *
     * @param \DateTime $object
     * @return string
     */
    protected function formatDbDate($object)
    {
        if (!$object instanceof \DateTime) {
            return $object;
        }
        return $object->format('Y-m-d H:i:s');
    }

    protected function formatDbArray($arr, $delimiter = '|', $trim = true)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        if (empty($arr)) {
            return null;
        }
        if ($trim) {
            foreach ($arr as $value) {
                $value = trim($value);
            }
        }
        $return = implode($delimiter, $arr);
        return $return;
    }

    protected function keyArray(array $a, $key, $unique = true)
    {
        $return = array();
        foreach ($a as $item) {
            if (!$unique) {
                if (isset($return[$item[$key]])) {
                    $return[$item[$key]][] = $item;
                } else {
                    $return[$item[$key]] = array($item);
                }
            } else {
                $return[$item[$key]] = $item;
            }
        }
        return $return;
    }

    /**
     * Get an instance of a TableGateway for a particular table name
     * @param string $tableName
     */
    protected function getTableGateway($tableName)
    {
        if (key_exists($tableName, $this->tableGatewaysCache)) {
            return $this->tableGatewaysCache[$tableName];
        }
        $gateway = new TableGateway($tableName, $this->adapter);
        //@todo is there a way to make sure the table exists?
        return $this->tableGatewaysCache[$tableName] = $gateway;
    }

    /**
     * Get the cache value
     * @return StorageInterface
     */
    public function getPersistentCache()
    {
        return $this->persistentCache;
    }

    /**
     *
     * @param StorageInterface $cache
     * @return self
     */
    public function setPersistentCache($cache)
    {
        $this->persistentCache = $cache;
        return $this;
    }

    /**
     * Get the maxItemsToCache value
     * @return int
     */
    public function getMaxItemsToCache()
    {
        return $this->maxItemsToCache;
    }

    /**
     *
     * @param int $maxItemsToCache
     * @return self
     */
    public function setMaxItemsToCache($maxItemsToCache)
    {
        $this->maxItemsToCache = $maxItemsToCache;
        return $this;
    }
}
