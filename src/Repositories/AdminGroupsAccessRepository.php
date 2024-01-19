<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class AdminGroupsAccessRepository extends Repository
{
    protected $tableName = 'admin_groups_access';

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(ActiveRow $adminGroup, ActiveRow $adminAccess)
    {
        return $this->insert([
            'admin_group_id' => $adminGroup->id,
            'admin_access_id' => $adminAccess->id,
            'created_at' => new \DateTime(),
        ]);
    }

    final public function exists(ActiveRow $adminGroup, ActiveRow $adminAccess): bool
    {
        return $this->getTable()->where([
            'admin_group_id' => $adminGroup->id,
            'admin_access_id' => $adminAccess->id,
        ])->count('*') > 0;
    }

    final public function deleteByAdminAccess(ActiveRow $adminAccess): void
    {
        $records = $this->getTable()->where('admin_access_id = ?', $adminAccess->id);
        foreach ($records as $record) {
            $this->delete($record);
        }
    }

    final public function deleteByGroup(ActiveRow $adminGroup, array $toDelete = [], array $toExclude = []): bool
    {
        $selection = $this->getTable()->where(['admin_group_id' => $adminGroup->id]);
        if (!empty($toDelete)) {
            $selection->where(['admin_access_id IN (?)' => $toDelete]);
        }
        if (!empty($toExclude)) {
            $selection->where(['admin_access_id NOT IN (?)' => $toExclude]);
        }

        // remove in foreach to generate audit log
        foreach ($selection as $row) {
            $this->delete($row);
        }

        return true;
    }
}
