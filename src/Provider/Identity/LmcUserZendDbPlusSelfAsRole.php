<?php

declare(strict_types=1);

namespace JUser\Provider\Identity;

use BjyAuthorize\Provider\Identity\LmcUserLaminasDb;

class LmcUserZendDbPlusSelfAsRole extends LmcUserLaminasDb
{
    public function getIdentityRoles()
    {
        $roles       = parent::getIdentityRoles();
        $authService = $this->userService->getAuthService();
        $identity    = $authService->getIdentity();
        if (isset($identity)) {
            $userId = $identity->getId();
            if (isset($userId)) {
                $roles[] = "user_$userId";
            }
        }
        return $roles;
    }
}
