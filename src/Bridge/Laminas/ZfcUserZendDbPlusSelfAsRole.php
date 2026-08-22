<?php

namespace JUser\Bridge\Laminas;

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
 * (which this class used to extend), minus the dependency on ZfcUser.
 *
 * It also used to append a `user_<id>` role, so an ACL rule could name one person.
 * That went on 2026-08-22 together with the provider that registered those roles —
 * see the note in this module's `config/module.config.php` for why, and note that
 * the two had to go **together**: what this method returns is handed to
 * `Laminas\Permissions\Acl\Acl::addRole()` as the identity's parent roles, and that
 * throws on a parent the ACL has never heard of. Leaving this line behind would
 * have been a 500 on every signed-in request, not a harmless leftover.
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
