<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\RetentionData;
use Nette\Utils\DateTime;

class ChangePasswordsLogsRepository extends Repository
{
    use RetentionData;

    const TYPE_CHANGE = 'change';
    const TYPE_RESET = 'reset';
    const TYPE_FORCE = 'force';
    const TYPE_SUSPICIOUS = 'suspicious';
    const TYPE_GIFT = 'gift';

    protected $tableName = 'change_passwords_logs';

    final public function add($user, $type, $oldPassword, $newPassword)
    {
        return $this->insert([
            'user_id' => $user->id,
            'created_at' => new \DateTime(),
            'type' => $type,
            'from_password' => $oldPassword,
            'to_password' => $newPassword,
        ]);
    }

    final public function totalUserLogs($userId)
    {
        return $this->userLogs($userId)->count('*');
    }

    final public function lastUserLogs($userId)
    {
        return $this->userLogs($userId)->order('created_at DESC')->limit(100);
    }

    final public function lastUserLog($userId, $type)
    {
        return $this->userLogs($userId)->where(['type' => $type])->order('created_at DESC')->limit(1)->fetch();
    }

    private function userLogs($userId)
    {
        return $this->getTable()->where(['user_id' => $userId]);
    }

    final public function removeOldData(): void
    {
        $records = $this->getTable()
            ->select('change_passwords_logs.id')
            ->where('user.active = ?', false)
            ->where('change_passwords_logs.created_at < ?', DateTime::from($this->getRetentionThreshold()));

        if ($records->fetchAll()) {
            $this->getTable()->where('id IN (?)', $records)->delete();
        }
    }
}
