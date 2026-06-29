<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class UserGroupsRepository extends Repository
{
    protected $tableName = 'user_groups';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        private readonly UsersRepository $usersRepository,
        private readonly GroupsRepository $groupsRepository,
    ) {
        parent::__construct($database);

        $this->auditLogRepository = $auditLogRepository;
    }

    final public function isMember(ActiveRow $groupRow, ActiveRow $userRow)
    {
        return $this->row($groupRow, $userRow)->count('*') > 0;
    }

    final public function addToGroup(ActiveRow $groupRow, ActiveRow $userRow)
    {
        if ($this->isMember($groupRow, $userRow)) {
            return false;
        }

        $softDeleted = $this->getTable()
            ->where(['user_id' => $userRow->id, 'group_id' => $groupRow->id])
            ->where('deleted_at IS NOT NULL')
            ->fetch();

        if ($softDeleted) {
            $this->update($softDeleted, [
                'deleted_at' => null,
                'created_at' => new \DateTime(),
            ]);
            return true;
        }

        $this->insert([
            'group_id' => $groupRow->id,
            'user_id' => $userRow->id,
            'created_at' => new \DateTime(),
        ]);

        return true;
    }

    final public function removeFromGroup(ActiveRow $groupRow, ActiveRow $userRow)
    {
        $row = $this->row($groupRow, $userRow)->fetch();
        if (!$row) {
            return false;
        }

        $this->update($row, ['deleted_at' => new \DateTime()]);

        return true;
    }

    final public function removeGroupsForUser(ActiveRow $userRow): int
    {
        return $this->getTable()
            ->where(['user_id' => $userRow->id])
            ->delete();
    }

    final public function userGroups(ActiveRow $userRow)
    {
        return $this->groupsRepository->getTable()
            ->where([':user_groups.user_id' => $userRow->id])
            ->where(':user_groups.deleted_at IS NULL')
            ->order(':user_groups.created_at');
    }

    final public function groupMembers(ActiveRow $groupRow)
    {
        return $this->usersRepository->getTable()
            ->where([':user_groups.group_id' => $groupRow->id])
            ->where(':user_groups.deleted_at IS NULL')
            ->order(':user_groups.created_at');
    }

    private function row(ActiveRow $groupRow, ActiveRow $userRow)
    {
        return $this->getTable()
            ->where(['user_id' => $userRow->id, 'group_id' => $groupRow->id])
            ->where('deleted_at IS NULL');
    }
}
