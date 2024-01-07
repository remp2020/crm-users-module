<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Models\Authorization\ApiAuthorizationInterface;

interface UsersApiAuthorizationInterface extends ApiAuthorizationInterface
{
    public function getAuthorizedUsers();
}
