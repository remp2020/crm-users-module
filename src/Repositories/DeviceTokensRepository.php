<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\UsersModule\Models\Auth\Access\TokenGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class DeviceTokensRepository extends Repository
{
    protected $tableName = 'device_tokens';

    final public function generate(string $deviceId): ActiveRow
    {
        $token = TokenGenerator::generate();
        return $this->add($deviceId, $token);
    }

    final public function add(string $deviceId, string $deviceToken): ActiveRow
    {
        return $this->insert([
            'device_id' => $deviceId,
            'created_at' => new DateTime(),
            'last_used_at' => new DateTime(),
            'token' => $deviceToken,
        ]);
    }

    final public function findByToken(string $token): ?ActiveRow
    {
        return $this->getTable()->where('token', $token)->fetch();
    }

    final public function findByDeviceId(string $deviceId): ?ActiveRow
    {
        return $this->getTable()->where('device_id', $deviceId)->fetch();
    }
}
