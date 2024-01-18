<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class AdminAccessRepository extends Repository
{
    protected $tableName = 'admin_access';

    /**
     * @return bool|int|ActiveRow
     */
    final public function add(string $resource, string $action, string $type, string $level = null)
    {
        $now = new DateTime();
        return $this->insert([
            'resource' => $resource,
            'action' => $action,
            'type' => $type,
            'level' => $level,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    final public function update(ActiveRow &$row, $data): bool
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function all(): Selection
    {
        return $this->getTable()->order('resource');
    }

    final public function findByResource(string $resource): Selection
    {
        return $this->getTable()
            ->where(['resource' => $resource]);
    }

    final public function findByResourceAndAction(string $resource, string $action): ?ActiveRow
    {
        return $this->getTable()
                ->where(['resource' => $resource, 'action' => $action])
                ->fetch() ?: null;
    }
}
