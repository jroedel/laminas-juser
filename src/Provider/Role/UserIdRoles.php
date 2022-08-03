<?php

declare(strict_types=1);

namespace JUser\Provider\Role;

use BjyAuthorize\Acl\Role;
use BjyAuthorize\Provider\Role\ProviderInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Webmozart\Assert\Assert;

class UserIdRoles implements ProviderInterface
{
    public function __construct(private TableGatewayInterface $tableGateway, private string $userTableName)
    {
    }

    /**
     * @return RoleInterface[]
     */
    public function getRoles()
    {
        // get roles associated with the logged-in user
        $sql = new Select();

        $sql->from($this->userTableName)
        ->columns(['user_id']);

        $results = $this->tableGateway->selectWith($sql);

        $roles = [];
        foreach ($results as $row) {
            $userId = $row['user_id'];
            Assert::integerish($userId);
            $roles[] = new Role("user_$userId");
        }

        return $roles;
    }
}
