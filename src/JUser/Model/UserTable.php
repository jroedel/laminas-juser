<?php
namespace JUser\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Filter\Boolean;

class UserTable
{
    /**
     *
     * @var AdapterInterface
     */
    protected $adapter;
    
    /**
     * @var TableGateway
     */
    protected $tableGateway;
    
    /**
     * @var TableGateway
     */
    protected $userGateway;
    
    /**
     * @var TableGateway
     */
    protected $userRoleGateway;

    /**
     * @var TableGateway
     */
    protected $roleGateway;
    
    /**
     * @var array
     */
    protected $userRoles;
    
    protected $usersCache = null;
    protected $userRolesCache = null;
    protected $userRolesSimpleCache = null;
    protected $rolesCache = null;
    protected $rolesSimpleCache = null;
    
    /**
     * 
     * @var \JUser\Entity\User
     */
    protected $actingUser;

    /**
     * @param TableGateway $userGateway
     * @param TableGateway $userRoleGateway
     * @param \JUser\Entity\User
     */
    public function __construct(TableGateway $userGateway, TableGateway $userRoleGateway, $actingUser = null)
    {
        $this->tableGateway = $userGateway;
        $this->userGateway = $userGateway;
        $this->userRoleGateway = $userRoleGateway;
        $this->adapter = $userGateway->getAdapter();
        $this->actingUser = $actingUser;
    }
    
    /**
     * Gets list of users
     * @return boolean[][]|unknown[][]|DateTime[][]|NULL[][]|number[][]
     */
	public function getUsers()
	{
        if ($this->usersCache) {
            return $this->usersCache;
        }
	    $sql = "SELECT `user_id`, `username`, `email`, 
`display_name`, `password`, `create_datetime`, create_by, `update_datetime`, update_by, 
`state`, `lang`, `PersID`, `email_verified`, `must_change_password`, `multi_person_user` FROM `user`";
	    $results = $this->fetchSome(null, $sql, null);
	    //manipulate column names
	    $users = array();
	    foreach ($results as $row) {
	        $id = $this->filterDbId($row['user_id']);
    	    $users[$id] = array(
    	        'userId'       => $id,
    	        'username'     => $row['username'],
    	        'email'        => $row['email'],
    	        'displayName'  => $row['display_name'],
    	        'password'     => $row['password'],
    	        'createdOn'    => $this->filterDbDate($row['create_datetime']),
    	        'createdBy'    => $this->filterDbInt($row['create_by']),
    	        'updatedOn'    => $this->filterDbDate($row['update_datetime']),
    	        'updatedBy'    => $this->filterDbInt($row['update_by']),
    	        'emailVerified'=> $this->filterDbBool($row['email_verified']),
    	        'active'       => $this->filterDbBool($row['state']),
    	        'languages'    => $this->filterDbArray($row['lang'], ';'),
    	        'personId'     => $this->filterDbId($row['PersID']),
    	        'roles'        => $this->getUserRoles($id, true),
    	        'rolesDetails' => $this->getUserRoles($id),
    	    );
	    }
	    $this->usersCache = $users;
	    return $users;
	}
	
	/**
	 * Get user properties
	 * @param int|string $id
	 */
	public function getUser($id)
	{
	    if (!$this->usersCache) {
	        $this->getUsers();
	    }
	    if (!isset($this->usersCache[$id])) {
	        return null;
	    }
	    return $this->usersCache[$id];
	}
	
	/**
	 * Get roles for a user
	 * caches user_role_linker table
	 * @param int $id
	 */
	public function getRoles($simple = false)
	{
	    if ($this->rolesCache) {
	        if ($simple) {
	            return $this->rolesSimpleCache;
	        } else {
	            return $this->rolesCache;
	        }
	    }
	    //manipulate column names
	    $sql = "SELECT `role_id`, `parent` FROM `user_role` ORDER BY `role_id`";
	    $results = $this->fetchSome(null, $sql, null);
	    $roles = array();
	    $rolesSimple = array();
	    foreach ($results as $row) {
	        $rolesSimple[] = $row['role_id'];
	        $roles[$row['role_id']] = array(
	            'roleId'   => $row['role_id'],
	            'parentId' => $row['parent'],
	        );
	    }
	    $this->rolesCache = $roles;
	    $this->rolesSimpleCache = $rolesSimple;
        if ($simple) {
            return $rolesSimple;
        } else {
           return $roles;
        }
	}
	
	public function createRole($data)
	{
	    $tableName     = 'user_role';
	    $tableGateway  = $this->getRoleTableGateway();
	    $scope         = 'Role';
	    $requiredCols  = array(
	        'roleId'
	    );
	    $updateCols = array(
	        'roleId'       => 'role_id',
	        'parent'       => 'parent',
	        'createdOn'    => 'create_datetime',
	        'createdBy'    => 'create_by',
	    );
	    $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope);
	    return $return;
	}
	
	public function getRolesValueOptions()
	{
	    $roles = $this->getRoles(false);
	    $return = array();
	    foreach ($roles as $role) {
	        $return[$role['roleId']] = $role['roleId'].
	           ($role['parentId'] ? ' (child of '.$role['parentId'].')' : '');
	    }
	    return $return;
	}
	
	/**
	 * Get roles for a user
	 * caches user_role_linker table
	 * @param int $id
	 */
	public function getUserRoles($id = null, $simple = false)
	{
	    if ($this->userRolesCache) {
	        if (is_null($id)) {
    	        if ($simple) {
    	            return $this->userRolesSimpleCache;
    	        } else {
    	            return $this->userRolesCache;
    	        }
	        }
	        if (!isset($this->userRolesCache[$id])) {
	            return null;
	        }
	        if ($simple) {
	            return $this->userRolesSimpleCache[$id];
	        } else {
	           return $this->userRolesCache[$id];
	        }
	    }
	    $sql = "SELECT `user_id`, `role_id`, `create_datetime`, `create_by` 
FROM `user_role_linker` ORDER BY `user_id`, `role_id`";
	    $results = $this->fetchSome(null, $sql, null);
	    //manipulate column names
	    $usersRoles = array();
	    $usersRolesSimple = array();
	    foreach ($results as $row) {
	        $thisId = $this->filterDbId($row['user_id']);
	        $thisRole = array(
	            'roleId'       => $row['role_id'],
	            'createdOn'    => $this->filterDbDate($row['create_datetime']),
	            'createdBy'    => $this->filterDbInt($row['create_by']),
	        );
	        if (isset($usersRoles[$thisId])) {
	            $usersRoles[$thisId][] = $thisRole;
	            $usersRolesSimple[$thisId][] = $row['role_id'];
	        } else {
                $usersRoles[$thisId] = array($thisRole);
	            $usersRolesSimple[$thisId] = array($row['role_id']);
	        }
	    }
	    $this->userRolesCache = $usersRoles;
	    $this->userRolesSimpleCache = $usersRolesSimple;
	    if (is_null($id)) {
	        if ($simple) {
	            return $usersRolesSimple;
	        } else {
	           return $usersRoles;
	        }
	    }
	    if (!isset($usersRoles[$id])) {
	        return null;
	    }
	    if ($simple) {
	        return $usersRolesSimple[$id];
	    } else {
	       return $usersRoles[$id];
	    }
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
	    if (!userId) {
	        throw new \InvalidArgumentException('Invalid user passed.');
	    }
	    if (!$roleId) {
	        throw new \InvalidArgumentException('Invalid role passed.');
	    }
 	    $user = $this->getUser($userId);
 	    if (!user) {
 	        return false;
 	    }
 	    if (!isset($user['roles']) || empty($user['roles'])) {
 	        return false;
 	    }
	    foreach ($user['roles'] as $role) {
	        if ($role == $roleId) {
	            return true;
	        }
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
	        if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) { //check if this column has updatedOn column
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
// 	        $this->reportChange($changes);
	        return $result;
	    }
	    return true;
	}
	
	/**
	 *
	 * @param int|string $id
	 * @param string[][] $data
	 */
	public function updateUser($id, $data) {
	    $tableName     = 'user';
	    $tableKey      = 'user_id';
	    $tableGateway  = $this->getUserTableGateway();
	    
	    if (!is_numeric($id)) {
	        throw new \InvalidArgumentException('Invalid user id provided.');
	    }
	    $user = $this->getUser($id);
	    if (!$user) {
	        throw new \InvalidArgumentException('No user provided.');
	    }
	    $updateCols = array(
	        'userId'       => $id,
	        'username'     => 'username',
	        'email'        => 'email',
	        'displayName'  => 'display_name',
// 	        'password'     => 'password',
	        'createdOn'    => 'create_datetime',
	        'createdBy'    => 'create_by',
	        'updatedOn'    => 'update_datetime',
	        'updatedBy'    => 'update_by',
	        'emailVerified'=> 'email_verified',
	        'active'       => 'state',
// 	        'languages'    => 'languages',
	        'personId'     => 'PersID',
	    );
	    $return = $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $user);
	    $this->updateUserRoles($data, $user);
	    return $return;
	}
	
    protected function updateUserRoles($newUser, $oldUser)
    {
        if (!$newUser || !is_array($newUser) || 
            !isset($newUser['userId']) || 
            !isset($newUser['roles']))
        {
            return 0;
        }
        $newRoles = $newUser['roles'];
        if ($oldUser && isset($oldUser['roles'])) {
            $oldRoles = $oldUser['roles'];
        } else {
            $oldRoles = array();
        }
        $allRoles = $this->getRoles(true);
        $tableGateway = $this->getUserRolesTableGateway();
        $roles = array();
        foreach ($allRoles as $role) {
            $roles[$role] = array(
                'old' => in_array($role, $oldRoles),
                'new' => in_array($role, $newRoles),
            );
        }
        $return = array();
        foreach ($roles as $role => $oldNew) {
            if ($oldNew['new'] === $oldNew['old'])
            { //if they're the same, we don't need to do anything
                continue;
            }
            $data = array('user_id' => $newUser['userId'], 'role_id' => $role);
            if ($oldNew['new'] && !$oldNew['old']) { //insert a role
                $result = 0;
                $data['create_datetime'] = $this->formatDbDate(new \DateTime(null, new \DateTimeZone('UTC')));
                if(is_object($this->actingUser)) {
                    $data['create_by'] = $this->actingUser->id;
                }
                $result = $tableGateway->insert($data);
                $return[] = array(
                    'method' => 'insert',
                    'data' => $data, 
                    'result' => $result
                );
                continue;
            }
            if (!$oldNew['new'] && $oldNew['old']) { //delete a role
                $result = $tableGateway->delete($data);
                $return[] = array(
                    'method' => 'delete',
                    'data' => $data, 
                    'result' => $result
                );
                continue;
            }
        }
        return count($return);
    }

	/**
	 *
	 * @param string[][] $data
	 */
	public function createUser($data) {
	    $tableName     = 'user';
	    $tableGateway  = $this->getUserTableGateway();
	    $scope         = 'User';
	    $requiredCols  = array(
	        'username', 'email', 'displayName', 'password'
	    );
	    $updateCols = array(
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
	        'active'       => 'state',
// 	        'languages'    => 'languages',
	        'personId'     => 'PersID',
	    );
	    $return = $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope);
	    $data['userId'] = $return;
	    $this->updateUserRoles($data, null);
	    return $return;
	}
	
	protected function createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope = null)
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
	        if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) { //check if this column has updatedOn column
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
        if (key_exists('createdOn', $updateCols) && !key_exists('createdOn', $data)) { //check if this column has updatedOn column
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
// 	        $this->reportChange($changeVals);
	        //create the roles associated with the newly created entity
// 	        if ($scope) {
// 	            $this->createAssociatedRoles($scope, $newId);
// 	        }
	        return $newId;
	    }
	    return false;
	}

    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     */
    public function deleteUser($id)
    {
        $result = $this->getUserRolesTableGateway()->delete(array('user_id' => $id));
    	$result = $this->getUserTableGateway()->delete(array('user_id' => $id));
    	return $result;
    }
    
    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     * @param string $newPass
     */
    public function updateUserPassword($id, $newPass)
    {
    	$result = $this->getUserTableGateway()->update(array('password' => $newPass), array('user_id' => $id));
    	return $result;
    }
    
    /**
     * 
     * @param array $data
     * @param \Traversable $currentRecord
     */
//     public function updateUser($data, $currentRecord)
//     {
//         $id = ( int ) $data['userId'];
//         if (!$id) {
//             throw new \InvalidArgumentException('No user id provided to update record.');
//         }
//         if (!$currentRecord) {
//             $currentRecord = $this->getUser($data['userId']);
//         }
//         if (!$currentRecord) {
//             throw new \InvalidArgumentException('User doesn\'t exists.');
//         }
//         $cleanData = array();
//         if ($data['username']) {
//             $cleanData['username'] =  $data['username'];
//         }
//         if ($data['email']) {
//             $cleanData['email'] =  $data['email'];
//         }
//         if ($data['displayName']) {
//             $cleanData['display_name'] =  $data['displayName'];
//         }
//         if ($data['state']) {
//             $cleanData['state'] =  $data['state'];
//         }
//         if ($data['password']) {
//             $cleanData['password'] =  $data['password'];
//         }
//         if ($data['lang']) {
//             $cleanData['lang'] =  $data['lang'];
//         }
//         if ($data['updateDateTime']) {
//             $cleanData['update_datetime'] =  $data['updateDateTime'];
//         }

//         $result = $this->getUserTableGateway()->update($data, array('user_id' => $id));
//         $this->insertUserChangeRecord($cleanData, $currentRecord);
//         if ($data['roles']) {
            
//         }
//     }
    

	/**
	 * @param Where|\Closure|string|array $where
	 * @param null|string
	 * @param null|array
	 * @return array
	 */
	public function fetchSome($where, $sql = null, $sqlArgs = null)
	{
	    if (is_null($where) && is_null($sql)) {
	        throw new \InvalidArgumentException('No query requested.');
	    }
	    if (!is_null($sql))
	    {
	        if (is_null($sqlArgs)) {
	            $sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes
	        }
            $result = $this->tableGateway->getAdapter()->query($sql, $sqlArgs);
	    } else {
	        $result = $this->tableGateway->select($where);
	    }
	
        $return = array();
        foreach ($result as $row) {
            $return[] = $row;
        }
        return $return;
	}
	
	protected function filterDbId($str)
	{
	    if (is_null($str) || $str === '' || $str == '0') {
	        return null;
	    }
	    return (int) $str;
	}

	protected function filterDbInt($str)
	{
	    if (is_null($str) || $str === '') {
	        return null;
	    }
	    return (int) $str;
	}
	
	protected function filterDbBool($str)
	{
	    if (is_null($str) || $str === '' || $str == '0') {
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
	    $tz = new \DateTimeZone('UTC');
	    if (is_null($str) || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
	        return null;
	    }
	    try {
	        $return = new \DateTime($str, $tz);
	    } catch(\Exception $e) {
	        $return = null;
	    }
	    return $return;
	}
	
	/**
	 * 
	 * @param string $str
	 * @return \DateTime
	 */
	protected function filterDbArray($str, $delimiter = ',')
	{
	    if (is_null($str) || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
	        return null;
	    }
	    try {
	        $return = explode($delimiter, $str);
	    } catch(\Exception $e) {
	        $return = null;
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
     * @return TableGateway
     */
    public function getRoleTableGateway()
    {
    	if (null == $this->roleGateway) {
    		$this->roleGateway = new TableGateway('user_role', $this->adapter);
    	}
    	return $this->roleGateway;
    }
    
    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setRoleTableGateway($gateway)
    {
    	$this->roleGateway = $gateway;
    	return $this;
    }
	
    /**
     * @return TableGateway
     */
    public function getUserRolesTableGateway()
    {
    	if (null == $this->userRoleGateway) {
    		$this->userRoleGateway = new TableGateway('user_role_linker', $this->adapter);
    	}
    	return $this->userRoleGateway;
    }
    
    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setUserRolesTableGateway($gateway)
    {
    	$this->userRoleGateway = $gateway;
    	return $this;
    }
    
    /**
     * @return TableGateway
     */
    public function getUserTableGateway()
    {
    	if (null == $this->userGateway) {
    		$this->userGateway = new TableGateway('user', $this->adapter);
    	}
    	return $this->userGateway;
    }
    
    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setUserTableGateway($gateway)
    {
    	$this->userGateway = $gateway;
    	return $this;
    }
}
