<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use DateTime;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class GroupsRepository extends Repository
{
    protected $tableName = 'groups';

    final public function add($groupName, $sorting = 10)
    {
        return $this->insert([
            'name' => $groupName,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function exists($name)
    {
        return $this->findByName($name) !== null;
    }

    final public function findByName(string $name): ?ActiveRow
    {
        return $this->getTable()
            ->where('name', $name)
            ->where('deleted_at IS NULL')
            ->fetch();
    }

    final public function find($id): ?\Crm\ApplicationModule\Models\Database\ActiveRow
    {
        /** @var \Crm\ApplicationModule\Models\Database\ActiveRow $result */
        $result = $this->getTable()->where(['id' => $id, 'deleted_at' => null])->fetch();
        return $result;
    }

    /**
     * @return Selection
     */
    final public function all()
    {
        return $this->getTable()
            ->where('deleted_at IS NULL')
            ->order('sorting');
    }

    final public function softDelete(ActiveRow $group)
    {
        return $this->update($group, [
            'deleted_at' => new DateTime(),
        ]);
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }
}
