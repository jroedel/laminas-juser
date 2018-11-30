<?php

namespace JUser\Provider\Identity;

use BjyAuthorize\Provider\Identity\ZfcUserZendDb;

class ZfcUserZendDbPlusSelfAsRole extends ZfcUserZendDb
{
    
    /**
     *
     * {@inheritDoc}
     * @see \BjyAuthorize\Provider\Identity\ZfcUserZendDb::getIdentityRoles()
     */
    public function getIdentityRoles()
    {
        $roles = parent::getIdentityRoles();
        $authService = $this->userService->getAuthService();
        $identity = $authService->getIdentity();
        if (isset($identity)) {
            $userId = $identity->getId();
            if (isset($userId)) {
                $roles[] = "user_$userId";
            }
        }
        return $roles;
    }
}
