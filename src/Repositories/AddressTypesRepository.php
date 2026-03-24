<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;

class AddressTypesRepository extends Repository
{
    protected $tableName = 'address_types';

    final public function add(string $type, string $title): ActiveRow
    {
        return $this->insert([
            'type' => $type,
            'title' => $title,
        ]);
    }

    /**
     * @return array<string, string>
     */
    final public function getPairs(): array
    {
        return $this->getTable()->order('sorting')->fetchPairs('type', 'title');
    }

    final public function findByType(string $type): ?ActiveRow
    {
        return $this->findBy('type', $type);
    }
}
