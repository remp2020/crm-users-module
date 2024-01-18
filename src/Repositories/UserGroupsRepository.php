<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Repository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class UserGroupsRepository extends Repository
{
    protected $tableName = 'user_groups';

    private $usersRepository;

    private $groupsRepository;

    public function __construct(Explorer $database, UsersRepository $usersRepository, GroupsRepository $groupsRepository)
    {
        parent::__construct($database);
        $this->usersRepository = $usersRepository;
        $this->groupsRepository = $groupsRepository;
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

        $this->insert([
            'group_id' => $groupRow->id,
            'user_id' => $userRow->id,
            'created_at' => new \DateTime(),
        ]);

        return true;
    }

    final public function removeFromGroup(ActiveRow $groupRow, ActiveRow $userRow)
    {
        if (!$this->isMember($groupRow, $userRow)) {
            return false;
        }

        $this->row($groupRow, $userRow)->delete();

        return true;
    }

    final public function userGroups(ActiveRow $userRow)
    {
        return $this->groupsRepository->getTable()->where([
            ':user_groups.user_id' => $userRow->id,
        ])->order(':user_groups.created_at');
    }

    final public function groupMembers(ActiveRow $groupRow)
    {
        return $this->usersRepository->getTable()->where([
            ':user_groups.group_id' => $groupRow->id,
        ])->order(':user_groups.created_at');
    }

    private function row(ActiveRow $groupRow, ActiveRow $userRow)
    {
        return $this->getTable()->where(['user_id' => $userRow->id, 'group_id' => $groupRow->id]);
    }
}
