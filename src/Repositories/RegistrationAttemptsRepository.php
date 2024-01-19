<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Utils\DateTime;

class RegistrationAttemptsRepository extends Repository
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OK = 'ok';
    public const STATUS_INVALID_EMAIL = 'invalid_email';
    public const STATUS_TAKEN_EMAIL = 'taken_email';
    public const STATUS_DEVICE_TOKEN_NOT_FOUND = 'device_token_not_found';
    public const STATUS_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    
    protected $tableName = 'registration_attempts';

    final public function insertAttempt($email, $userId, $source, $status, $ip, $userAgent, $dateTime)
    {
        return $this->getTable()->insert([
            'email' => $email,
            'user_id' => $userId,
            'created_at' => $dateTime,
            'status' => $status,
            'source' => $source,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    final public function getAttemptsCount(string $ip, DateTime $from): int
    {
        return $this->getTable()->where([
                'ip' => $ip,
                'created_at > ?' => $from
            ])
            ->count('*');
    }
}
