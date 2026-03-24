<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class AdminGroupsRepository extends Repository
{
    protected $tableName = 'admin_groups';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        ?Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add($name, $sorting = 100): ActiveRow
    {
        return $this->insert([
            'name' => $name,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function update(ActiveRow &$row, $data): bool
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function findByName($name): ?ActiveRow
    {
        return $this->getTable()->where(['name' => $name])->limit(1)->fetch();
    }

    final public function all(): Selection
    {
        return $this->getTable()->order('sorting ASC');
    }
}
