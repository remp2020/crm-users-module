<?php

namespace Crm\UsersModule\Auth\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class AdminUserGroupsRepository extends Repository
{
    protected $tableName = 'admin_user_groups';

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    public function add(ActiveRow $adminGroup, ActiveRow $user)
    {
        $now = new \DateTime();
        return $this->insert([
            'admin_group_id' => $adminGroup->id,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function exists(ActiveRow $adminGroup, ActiveRow $user)
    {
        return $this->getTable()->where([
                'admin_group_id' => $adminGroup->id,
                'user_id' => $user->id
            ])->count('*') > 0;
    }

    public function remove(ActiveRow $adminGroup, ActiveRow $user): bool
    {
        $row = $this->getTable()->where([
            'admin_group_id' => $adminGroup->id,
            'user_id' => $user->id
        ])->fetch();
        if (!$row) {
            return false;
        }

        return $this->delete($row);
    }

    public function removeGroupsForUser(ActiveRow $userRow): int
    {
        return $this->getTable()
            ->where(['user_id' => $userRow->id])
            ->delete();
    }
}
