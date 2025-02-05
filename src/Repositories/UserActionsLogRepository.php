<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\RetentionData;
use Nette\Utils\DateTime;

class UserActionsLogRepository extends Repository
{
    use RetentionData;

    protected $tableName = 'user_actions_log';

    final public function add($userId, $action, $params = [])
    {
        return $this->insert([
            'user_id' => $userId,
            'created_at' => new DateTime(),
            'action' => $action,
            'params' => json_encode($params),
        ]);
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function totalCounts()
    {
        return $this->getTable()->group('action')->select('action, COUNT(*) AS count');
    }

    final public function removeOldData(): void
    {
        $ids = $this->getTable()
            ->select('user_actions_log.id')
            ->where('user.active = ?', false)
            ->where('user_actions_log.created_at < ?', DateTime::from($this->getRetentionThreshold()))
            ->fetchPairs('id', 'id');

        if (count($ids)) {
            $this->getTable()->where('id IN (?)', $ids)->delete();
        }
    }

    final public function availableSubscriptionTypes()
    {
        return $this->getTable()->select("DISTINCT(JSON_EXTRACT(params, \"$.subscription_type_id\")) AS subscription_type_id")->fetchPairs(null, 'subscription_type_id');
    }
}
