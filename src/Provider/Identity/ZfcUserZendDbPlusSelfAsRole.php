<?php

namespace JUser\Provider\Identity;

use BjyAuthorize\Exception\InvalidRoleException;
use BjyAuthorize\Provider\Identity\ProviderInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Permissions\Acl\Role\RoleInterface;

/**
 * Identity provider based on a Laminas\Db adapter.
 *
 * Behaves exactly like the former BjyAuthorize\Provider\Identity\ZfcUserZendDb
 * (which this class used to extend), plus it adds a "user_<id>" role so that
 * ACL rules can target an individual user. It no longer depends on ZfcUser.
 */
class ZfcUserZendDbPlusSelfAsRole implements ProviderInterface
{
    /** @var AuthenticationService $authService */
    protected $authService;

    /** @var string|RoleInterface $defaultRole */
    protected $defaultRole;

    /** @var string $tableName */
    protected $tableName = 'user_role_linker';

    /** @var TableGateway $tableGateway */
    private $tableGateway;

    public function __construct(TableGateway $tableGateway, AuthenticationService $authService)
    {
        $this->tableGateway = $tableGateway;
        $this->authService = $authService;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentityRoles()
    {
        if (! $this->authService->hasIdentity()) {
            return [$this->getDefaultRole()];
        }

        $identity = $this->authService->getIdentity();

        // get roles associated with the logged in user
        $sql = new Select();

        $sql->from($this->tableName);
        // @todo these fields should eventually be configurable
        $sql->join('user_role', 'user_role.id = ' . $this->tableName . '.role_id');
        $sql->where(['user_id' => $identity->getId()]);

        $results = $this->tableGateway->selectWith($sql);

        $roles = [];

        foreach ($results as $role) {
            $roles[] = $role['role_id'];
        }

        //let the user be a role unto themselves, see JUser\Provider\Role\UserIdRoles
        $userId = $identity->getId();
        if (isset($userId)) {
            $roles[] = "user_$userId";
        }

        return $roles;
    }

    /**
     * @return string|RoleInterface
     */
    public function getDefaultRole()
    {
        return $this->defaultRole;
    }

    /**
     * @param string|RoleInterface $defaultRole
     * @throws InvalidRoleException
     */
    public function setDefaultRole($defaultRole)
    {
        if (! ($defaultRole instanceof RoleInterface || is_string($defaultRole))) {
            throw InvalidRoleException::invalidRoleInstance($defaultRole);
        }

        $this->defaultRole = $defaultRole;
    }
}
