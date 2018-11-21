<?php

namespace JUser\Provider\Role;

use Zend\Db\Sql\Select;
use BjyAuthorize\Provider\Role\ProviderInterface;
use Zend\Db\TableGateway\TableGatewayInterface;
use BjyAuthorize\Acl\Role;

class UserIdRoles implements ProviderInterface
{
    /**
     * 
     * @var TableGatewayInterface $tableGateway
     */
    protected $tableGateway;
    
    protected $tableName = 'user';
    
    public function __construct($tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }
    
    public function getRoles()
    {
        // get roles associated with the logged in user
        $sql = new Select();
        
        $sql->from($this->tableName)
        ->columns(['user_id']);
        
        $results = $this->tableGateway->selectWith($sql);
        
        $roles = [];
        foreach ($results as $row) {
            $userId = $row['user_id'];
            $roles[] = new Role("user_$userId");
        }
        
        return $roles;
    }
}
