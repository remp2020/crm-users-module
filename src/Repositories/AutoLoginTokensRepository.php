<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\RetentionData;
use Nette\Database\Table\ActiveRow;

class AutoLoginTokensRepository extends Repository
{
    protected $tableName = 'autologin_tokens';

    use RetentionData;

    final public function add($token, ActiveRow $user, $validFrom, $validTo, $maxCount = 1)
    {
        return $this->insert([
            'token' => $token,
            'user_id' => $user->id,
            'email' => $user->email,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'max_count' => $maxCount,
            'used_count' => 0,
            'created_at' => new \DateTime(),
        ]);
    }

    final public function userTokens($userId)
    {
        return $this->getTable()->where('user_id', $userId)->order('valid_to DESC');
    }

    final public function deleteAll($userId)
    {
        return $this->getTable()->where('user_id', $userId)->delete();
    }
}
