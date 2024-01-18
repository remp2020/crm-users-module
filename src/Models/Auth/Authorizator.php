<?php

namespace Crm\UsersModule\Models\Auth;

class Authorizator implements \Nette\Security\Authorizator
{
    private $permissions;

    public function __construct(Permissions $permissions)
    {
        $this->permissions = $permissions;
    }

    public function isAllowed($role, $resource, $privilege): bool
    {
        return $this->permissions->allowed($role, $resource, $privilege);
    }
}
