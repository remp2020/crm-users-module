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
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function exists($name)
    {
        return $this->getTable()->where(['name' => $name])->count('*') > 0;
    }

    /**
     * @return Selection
     */
    final public function all()
    {
        return $this->getTable()->order('sorting');
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }
}
