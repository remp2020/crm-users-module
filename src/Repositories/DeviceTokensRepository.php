<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Nette\Utils\DateTime;

class DeviceTokensRepository extends Repository
{
    protected $tableName = 'device_tokens';

    final public function generate(string $deviceId)
    {
        $token = TokenGenerator::generate();
        return $this->add($deviceId, $token);
    }

    final public function add(string $deviceId, string $deviceToken)
    {
        return $this->insert([
            'device_id' => $deviceId,
            'created_at' => new DateTime(),
            'last_used_at' => new DateTime(),
            'token' => $deviceToken,
        ]);
    }

    final public function findByToken(string $token)
    {
        return $this->getTable()->where('token', $token)->fetch();
    }

    final public function findByDeviceId(string $deviceId)
    {
        return $this->getTable()->where('device_id', $deviceId)->fetch();
    }
}
