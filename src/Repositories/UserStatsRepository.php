<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class UserStatsRepository extends Repository
{
    protected $tableName = 'user_stats';

    final public function upsertUsersValues(string $key, array $usersValues, ?DateTime $now = null): void
    {
        if (!$now) {
            $now = new DateTime();
        }
        foreach (array_chunk($usersValues, 5000, true) as $countsChunk) {
            $data = [];
            foreach ($countsChunk as $userId => $count) {
                $data[] = [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'value' => $count,
                    'user_id' => $userId,
                    'key' => $key,
                ];
            }
            $this->database->query(
                'INSERT INTO `user_stats`',
                $data,
                'ON DUPLICATE KEY UPDATE `value`= VALUES(`value`), updated_at = VALUES(updated_at)'
            );
        }
    }

    final public function userStats($user): array
    {
        if ($user instanceof ActiveRow) {
            $user = $user->id;
        }
        return $this->getTable()
            ->where(['user_id' => $user])
            ->order('key ASC')
            ->fetchPairs('key', 'value');
    }
}
