<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Repository;
use Nette\Database\IRow;

class DeviceAccessTokensRepository extends Repository
{
    protected $tableName = 'device_access_tokens';

    final public function pairAccessToken(IRow $accessToken, IRow $deviceToken)
    {
        $this->insert([
            'device_token_id' => $deviceToken->id,
            'access_token_id' => $accessToken->id,
        ]);
    }
}
