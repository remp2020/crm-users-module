<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;

class AddressTypesRepository extends Repository
{
    protected $tableName = 'address_types';

    final public function add(string $type, string $title)
    {
        return $this->insert([
            'type' => $type,
            'title' => $title,
        ]);
    }

    final public function getPairs()
    {
        return $this->getTable()->order('sorting')->fetchPairs('type', 'title');
    }

    final public function findByType(string $type)
    {
        return $this->findBy('type', $type);
    }
}
