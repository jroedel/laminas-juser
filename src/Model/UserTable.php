<?php

declare(strict_types=1);

namespace JUser\Model;

use DateTime;
use DateTimeZone;
use Exception;
use JUser\Service\Mailer;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Select;
use Laminas\Log\LoggerInterface;
use LmcUser\Entity\UserInterface;
use LmcUser\Mapper\UserInterface as UserMapperInterface;
use SionModel\Db\Model\SionTable;
use SionModel\I18n\LanguageSupport;
use SionModel\Problem\EntityProblem;
use SionModel\Service\SionCacheService;
use Webmozart\Assert\Assert;

use function array_keys;
use function count;
use function current;
use function debug_backtrace;
use function in_array;
use function is_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

class UserTable extends SionTable implements UserMapperInterface
{
    public const USER_TABLE_NAME             = 'user';
    public const ROLE_TABLE_NAME             = 'user_role';
    public const USER_ROLE_LINKER_TABLE_NAME = 'user_role_linker';

    public function __construct(
        AdapterInterface $adapter,
        array $entitySpecifications,
        SionCacheService $sionCacheService,
        EntityProblem $entityProblemPrototype,
        ?UserTable $userTable,
        LanguageSupport $languageSupport,
        LoggerInterface $logger,
        ?int $actingUserId,
        array $config,
        private Mailer $mailer
    ) {
        parent::__construct(
            adapter: $adapter,
            entitySpecifications: $entitySpecifications,
            sionCacheService: $sionCacheService,
            entityProblemPrototype: $entityProblemPrototype,
            userTable: $userTable,
            languageSupport: $languageSupport,
            logger: $logger,
            actingUserId: $actingUserId,
            generalConfig: $config
        );
    }

    public function findByEmail($email): User|null
    {
        $dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $dbt[1]['function'] ?? null;
        $this->logger->debug("JUser: Looking up user by email", ['email' => $email, 'caller' => $caller]);
        $results = $this->queryObjects('user', ['email' => $email]);
        if (! isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray  = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }

        //if we've got an inactive user, notify the user to look for a verification email
        if ('authenticate' === $caller && 0 === $userArray['active'] && ! $userArray['emailVerified']) {
            $this->getMailer()->onInactiveUser($userObject, $this);
        }

        //@todo trigger find event
        return $userObject;
    }

    /**
     * @param string $username
     */
    public function findByUsername($username): User|null
    {
        $dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $dbt[1]['function'] ?? null;
        $this->logger->debug("JUser: Looking up user by username", ['username' => $username, 'caller' => $caller]);
        $results = $this->queryObjects('user', ['username' => $username]);
        if (! isset($results) || empty($results)) {
            return null;
        }
        $this->linkUsers($results);
        $userArray  = current($results);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }

        //notify the user to look for a verification email
        if ('authenticate' === $caller && 0 === $userArray['active'] && ! $userArray['emailVerified']) {
            $this->getMailer()->onInactiveUser($userObject, $this);
        }

        //@todo trigger find event
        return $userObject;
    }

    /**
     * @param string|int $id
     */
    public function findById($id): User|null
    {
        $this->logger->debug("JUser: Looking up user by id", ['id' => $id]);
        $userArray  = $this->getUser($id);
        $userObject = null;
        if (isset($userArray) && is_array($userArray)) {
            $userObject = new User($userArray);
        }
        //@todo trigger find event
        return $userObject;
    }

    public function insertUser(User $user)
    {
        //figure out what the calling function is. If a user is registering, trigger the email here
        $data = $user->getArrayCopy();
        unset($data['userId']); //we don't have a userId yet
        $dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $dbt[1]['function'] ?? null;
        if (isset($this->logger)) {
            $this->logger->info(
                "JUser: About to insert a new user.",
                ['caller' => $caller, 'email' => $user->getEmail()]
            );
        }
        if (empty($data['roles'])) {
            $defaultRoles      = $this->getDefaultRoles();
            $data['roles']     = $defaultRoles;
            $data['rolesList'] = array_keys($defaultRoles);
        }
        $result = $this->createEntity('user', $data);
        $this->logger->info("JUser: Finished inserting a new user.", ['result' => $result]);
        if ('register' === $caller) {
            //we need to send the registration email
            try {
                //@todo this could be better to schedule with cron, to avoid making the user wait for the send
                $this->getMailer()->onRegister($user);
            } catch (Exception $e) {
                $this->logger->err(
                    "JUser: Exception thrown while triggering verification email.",
                    ['exception' => $e]
                );
            }
        }
        return $result;
    }

    public function updateUser(UserInterface $user)
    {
        $data = $user->getArrayCopy();
        if (isset($this->logger)) {
            $dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $dbt[1]['function'] ?? null;
            $this->logger->info("About to update a user.", ['caller' => $caller, 'user' => $user]);
        }
        $result = $this->updateEntity('user', $data['userId'], $data);
        if (false === $result) {
            if (isset($this->logger)) {
                $this->logger->err("Failed updating a user.", ['result' => $result, 'user' => $user]);
            }
            throw new Exception('Error inserting a new user.');
        } else {
            if (isset($this->logger)) {
                $this->logger->info("Finished updating a user.", ['result' => $result]);
            }
        }
        return $result;
    }

    public function insert(UserInterface $user)
    {
        return $this->insertUser($user);
    }

    public function update(UserInterface $user)
    {
        return $this->updateUser($user);
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
            if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
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
            $this->sionCacheService->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role', 'user-role-link']);
        }
        return $objects;
    }

    /**
     * Add role data to an array of user arrays (the array must be keyed on the userId)
     *
     * @param array $users
     * @return void
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

    public function linkUser(array &$user): void
    {
        $roleLinks = $this->queryObjects('user-role-link', ['userId' => $user['userId']]);
        foreach ($roleLinks as $link) {
            $user['roles'][$link['roleId']] = $link;
        }

        $user['rolesList'] = array_keys($user['roles']);
    }

    /**
     * Get an associative array of giving the username of each userId in the user table
     *
     * @param array $ids
     * @return string[]
     */
    public function getUsernames(array $ids = [])
    {
        $cacheKey = 'usernames';
        if (empty($ids)) {
            if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
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
            $this->sionCacheService->cacheEntityObjects($cacheKey, $usernames, ['user']);
        }
        return $usernames;
    }

    /**
     * @return (DateTime|int|array|bool|int|mixed|null)[]
     * @psalm-return array{userId: mixed, username: mixed, email: mixed,
     *     displayName: mixed, password: mixed, createdOn: DateTime,
     *     createdBy: (int|null), updatedOn: DateTime, updatedBy: (int|null),
     * emailVerified: bool, mustChangePassword: bool, isMultiPersonUser: bool,
     * verificationToken: mixed, verificationExpiration: DateTime, active: bool,
     * personId: (int|null), roles: array<empty, empty>, rolesList: array<empty, empty>}
     */
    protected function processUserRow($row): array
    {
        return [
            'userId'                 => $row['user_id'],
            'username'               => $row['username'],
            'email'                  => $row['email'],
            'displayName'            => $row['display_name'],
            'password'               => $row['password'],
            'createdOn'              => $this->filterDbDate($row['create_datetime']),
            'createdBy'              => $this->filterDbInt($row['create_by']),
            'updatedOn'              => $this->filterDbDate($row['update_datetime']),
            'updatedBy'              => $this->filterDbInt($row['update_by']),
            'emailVerified'          => $this->filterDbBool($row['email_verified']),
            'mustChangePassword'     => $this->filterDbBool($row['must_change_password']),
            'isMultiPersonUser'      => $this->filterDbBool($row['multi_person_user']),
            'verificationToken'      => $row['verification_token'],
            'verificationExpiration' => $this->filterDbDate($row['verification_expiration']),
            'active'                 => $this->filterDbInt($row['state']),
            'personId'               => $this->filterDbId($row['PersID']),
            'roles'                  => [],
            'rolesList'              => [],
        ];
    }

    protected function userPostprocessor($data, $newData, $entityAction): void
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
                        'userId'   => $data['userId'],
                        'oldRoles' => $newData['rolesList'] ?? [],
                        'newRoles' => $data['rolesList'] ?? [],
                    ]
                );
            }
            $this->updateUserRoles($data, $newData); //this function will clear cache
        }
    }

    /**
     * Get user properties
     *
     * @param int|string $id
     * @return mixed[]
     */
    public function getUser($id)
    {
        //see if we can grab the user out of the cache
        $cacheKey = 'all-linked-users';
        if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
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
     *
     * @param int|string $id
     */
    public function getUserFromToken($token)
    {
        $results = $this->queryObjects('user', ['verificationToken' => $token]);
        //it should be exactly 1. If there are duplicate tokens floating, we err on the safe side
        if (1 === count($results)) {
            $this->linkUsers($results);
            return current($results);
        }
        return null;
    }

    /**
     * no validation of id
     *
     * @todo report errors
     * @param int|string $id
     */
    public function deleteUser($id): int
    {
        $result = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME)
        ->delete(['user_id' => $id]);
        $result = $this->getTableGateway(self::USER_TABLE_NAME)
        ->delete(['user_id' => $id]);
        $this->sionCacheService->removeDependentCacheItems(['user']);
        return $result;
    }

    /**
     * Gets list of roles
     *
     * @return mixed[]
     */
    public function getRoles()
    {
        $cacheKey = 'role';
        if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $objects = $this->getObjects('user-role');
        $this->linkRoles($objects);

        $this->sionCacheService->cacheEntityObjects($cacheKey, $objects, ['user-role']);
        return $objects;
    }

    public function linkRoles(array &$objects): void
    {
        foreach ($objects as $roleId => $object) {
            if (isset($object['parentId']) && isset($objects[$object['parentId']])) {
                $objects[$roleId]['parentName'] = $objects[$object['parentId']]['name'];
            }
        }
    }

    /**
     * @return array[]
     * @psalm-return array<array>
     */
    public function getDefaultRoles(): array
    {
        return $this->queryObjects('user-role', ['isDefault' => '1']);
    }

    /**
     * @return (DateTime|int|bool|int|mixed|null)[]
     * @psalm-return array{roleId: (int|null), name: mixed, isDefault: bool,
     * parentId: (int|null), createdOn: DateTime, createdBy: (int|null), parentName: null}
     */
    protected function processRoleRow($row): array
    {
        return [
            'roleId'     => $this->filterDbId($row['id']),
            'name'       => $row['role_id'],
            'isDefault'  => $this->filterDbBool($row['is_default']),
            'parentId'   => $this->filterDbId($row['parent_id']),
            'createdOn'  => $this->filterDbDate($row['create_datetime']),
            'createdBy'  => $this->filterDbInt($row['create_by']),
            'parentName' => null,
        ];
    }

    public function getRolesValueOptions()
    {
        $cacheKey = 'roles-value-options';
        if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $roles  = $this->getRoles();
        $return = [];
        foreach ($roles as $role) {
            $return[$role['roleId']] = $role['name']
               . ($role['parentId'] ? ' (child of ' . $role['parentName'] . ')' : '');
        }
        $this->sionCacheService->cacheEntityObjects($cacheKey, $return, ['user-role']);
        return $return;
    }

    /**
     * Gets list of user role links
     *
     * @return mixed[]
     */
    protected function getUserRoleLinker($userIds = [])
    {
        $cacheKey = 'user-role-linker';
        if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $gateway = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME);
        $select  = $this->getSelectPrototype('user-role-link');
        if (! empty($userIds)) {
            $select->where(['user_id' => $userIds]);
        }
        $results = $gateway->selectWith($select);
        //manipulate column names
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processUserRoleLinkerRow($row);
            $objects[]    = $processedRow;
        }
        $this->sionCacheService->cacheEntityObjects($cacheKey, $objects, ['user', 'user-role', 'user-role-link']);
        return $objects;
    }

    /**
     * @return (DateTime|int|bool|int|mixed|null)[]
     * @psalm-return array{linkId: (int|null), userId: (int|null), roleId: (int|null),
     * createdOn: DateTime, createdBy: (int|null), name: mixed, isDefault: bool, parentId: (int|null)}
     */
    protected function processUserRoleLinkerRow(array $row): array
    {
        return [
            'linkId'    => $this->filterDbId($row['id']),
            'userId'    => $this->filterDbId($row['user_id']),
            'roleId'    => $this->filterDbId($row['role_id']),
            'createdOn' => $this->filterDbDate($row['create_datetime']),
            'createdBy' => $this->filterDbInt($row['create_by']),

            //from user_role
            'name'      => $row['role_name'],
            'isDefault' => $this->filterDbBool($row['is_default']),
            'parentId'  => $this->filterDbId($row['parent_id']),
        ];
    }

    /**
     * Check if a user has a certain role
     */
    public function userHasRole(int $userId, int $roleId): bool
    {
        Assert::greaterThan($userId, 0);
        Assert::greaterThan($roleId, 0);
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
     *
     * @param array $newUser
     * @param array $oldUser
     * @psalm-return 0|positive-int
     */
    protected function updateUserRoles(array $newUser, array $oldUser): int
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
        $allRoles     = $this->getRoles();
        $allRoleIds   = array_keys($allRoles);
        $tableGateway = $this->getTableGateway(self::USER_ROLE_LINKER_TABLE_NAME);
        $roles        = [];
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
                $data['create_datetime'] = $this->formatDbDate(new DateTime('now', new DateTimeZone('UTC')));
                if (isset($this->actingUserId)) {
                    $data['create_by'] = $this->actingUserId;
                }
                $result   = $tableGateway->insert($data);
                $return[] = [
                    'method' => 'insert',
                    'data'   => $data,
                    'result' => $result,
                ];
                continue;
            }
            if (! $oldNew['new'] && $oldNew['old']) { //delete a role
                $result   = $tableGateway->delete($data);
                $return[] = [
                    'method' => 'delete',
                    'data'   => $data,
                    'result' => $result,
                ];
            }
        }
        if (isset($this->logger)) {
            $this->logger->info("JUser: Updated user roles.", ['result' => $return]);
        }
        /*
         * Even though SionTable::updateEntity should clear cache after an update, this function
         * could be called from somewhere else. Clear the cache just in case
         */
        $this->sionCacheService->removeDependentCacheItems(['user']);

        return count($return);
    }

    /**
     * no validation of id
     *
     * @todo report errors
     * @param int|string $id
     * @param string $newPass
     */
    public function updateUserPassword($id, $newPass): int
    {
        $result = $this->getTableGateway(self::USER_TABLE_NAME)
            ->update(['password' => $newPass], ['user_id' => $id]);
        $this->sionCacheService->removeDependentCacheItems(['user']);
        return $result;
    }

    protected function getSelectPrototype(string $entity): Select
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
     * @throws Exception
     * @return Mailer
     */
    public function getMailer()
    {
        if (! isset($this->mailer)) {
            throw new Exception('The mailer is not set.');
        }
        return $this->mailer;
    }

    /**
     * @return self
     */
    public function setMailer(Mailer $mailer)
    {
        $this->mailer = $mailer;
        return $this;
    }
}
