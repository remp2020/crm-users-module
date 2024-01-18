<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\UsersModule\Repositories\AdminGroupsRepository;
use Nette\Security\Authorizator;
use Nette\Security\Permission;
use Tracy\Debugger;

class Permissions
{
    /** @var Permission  */
    private $acl;

    /** @var AdminGroupsRepository  */
    private $adminGroupsRepository;

    public function __construct(AdminGroupsRepository $adminGroupsRepository)
    {
        $this->adminGroupsRepository = $adminGroupsRepository;
    }

    public function allowed($role, $resource, $privilege)
    {
        if (!$this->acl) {
            $this->init();
        }
        if ($resource === Authorizator::ALLOW && $privilege === Authorizator::ALLOW) {
            return true;
        }
        if (!$this->acl->hasResource($resource)) {
            Debugger::log("Access Resource '$resource'' not found!", 'exception');
            return false;
        }
        if (!$this->acl->hasRole($role)) {
            Debugger::log("Access Role '$role'' not found!", 'exception');
            return false;
        }

        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    private function init()
    {
        $this->acl = new Permission();
        $adminGroups = $this->adminGroupsRepository->all();
        foreach ($adminGroups as $group) {
            $groupName = $group->name;
            $this->acl->addRole($groupName);
            $groupAccesses = $group->related('admin_groups_access');
            foreach ($groupAccesses as $groupAccess) {
                $resource = $groupAccess->admin_access->resource;
                $privilege = $groupAccess->admin_access->action;
                if (!$this->acl->hasResource($resource)) {
                    $this->acl->addResource($resource);
                }
                $this->acl->allow($groupName, $resource, $privilege);
            }
        }
    }
}
