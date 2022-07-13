<?php

declare(strict_types=1);

namespace JUser\Entity;

use BjyAuthorize\Acl\HierarchicalRoleInterface;

class Role implements HierarchicalRoleInterface
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $roleId;

    /** @var Role */
    protected $parent;

    /**
     * Get the id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the id.
     *
     * @param int $id
     * @return void
     */
    public function setId($id)
    {
        $this->id = (int) $id;
    }

    /**
     * Get the role id.
     *
     * @return string
     */
    public function getRoleId()
    {
        return $this->roleId;
    }

    /**
     * Set the role id.
     *
     * @param string $roleId
     * @return void
     */
    public function setRoleId($roleId)
    {
        $this->roleId = (string) $roleId;
    }

    /**
     * Get the parent role
     *
     * @return Role
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set the parent role.
     *
     * @param Role $role
     * @return void
     */
    public function setParent(Role $parent)
    {
        $this->parent = $parent;
    }
}
