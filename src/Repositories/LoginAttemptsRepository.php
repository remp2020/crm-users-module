<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use DeviceDetector\DeviceDetector;

class LoginAttemptsRepository extends Repository
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OK = 'ok';
    public const STATUS_API_OK = 'api_ok';
    public const STATUS_NOT_FOUND_EMAIL = 'not_found_email';
    public const STATUS_UNCLAIMED_USER = 'unclaimed_user';
    public const STATUS_WRONG_PASS = 'wrong_pass';
    public const STATUS_INACTIVE_USER = 'inactive_user';
    public const STATUS_TOKEN_DATE_EXPIRED = 'token_date_expired';
    public const STATUS_TOKEN_NOT_FOUND = 'token_not_found';
    public const STATUS_TOKEN_COUNT_EXPIRED = 'token_count_expired';
    public const STATUS_TOKEN_OK = 'token_ok';
    public const STATUS_ACCESS_TOKEN_OK = 'access_token_ok';
    public const STATUS_LOGIN_AFTER_SIGN_UP = 'login_after_sign_up';
    public const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';

    protected $tableName = 'login_attempts';

    final public function okStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_API_OK,
            self::STATUS_TOKEN_OK,
            self::STATUS_ACCESS_TOKEN_OK,
            self::STATUS_LOGIN_AFTER_SIGN_UP,
        ];
    }

    final public function statuses(): array
    {
        return [
            self::STATUS_UNKNOWN,
            self::STATUS_OK,
            self::STATUS_API_OK,
            self::STATUS_NOT_FOUND_EMAIL,
            self::STATUS_UNCLAIMED_USER,
            self::STATUS_WRONG_PASS,
            self::STATUS_INACTIVE_USER,
            self::STATUS_TOKEN_DATE_EXPIRED,
            self::STATUS_TOKEN_NOT_FOUND,
            self::STATUS_TOKEN_COUNT_EXPIRED,
            self::STATUS_TOKEN_OK,
            self::STATUS_ACCESS_TOKEN_OK,
            self::STATUS_LOGIN_AFTER_SIGN_UP,
            self::RATE_LIMIT_EXCEEDED,
        ];
    }

    final public function okStatus($status): bool
    {
        return in_array($status, $this->okStatuses(), true);
    }

    final public function insertAttempt($email, $userId, $source, $status, $ip, $userAgent, $dateTime, $message = null)
    {
        $browser = null;
        $browserVersion = null;
        $os = null;
        $device = null;
        $isMobile = null;
        if ($userAgent) {
            $deviceDetector = new DeviceDetector($userAgent);
            $deviceDetector->parse();

            $isMobile = $deviceDetector->isMobile();
            $browser = $deviceDetector->getClient('name');
            $browserVersion = $deviceDetector->getClient('version');
            $os = $deviceDetector->getOs('name');
            $device = $deviceDetector->getDeviceName();
        }

        return $this->getTable()->insert([
            'email' => $email,
            'user_id' => $userId,
            'created_at' => $dateTime,
            'status' => $status,
            'source' => $source,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'message' => $message,
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'device' => $device,
            'is_mobile' => $isMobile,
        ]);
    }

    final public function all()
    {
        return $this->getTable()->order('id ASC');
    }

    final public function totalUserAttempts($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->count('*');
    }

    final public function lastUserAttempt($userId, $count = 100)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC')->limit($count);
    }

    final public function userIps($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->group('ip');
    }

    final public function userAgents($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->group('user_agent');
    }

    final public function lastIpAttempts($ip, $count)
    {
        return $this->getTable()->where(['ip' => $ip])->order('created_at DESC')->limit($count);
    }

    final public function deleteAll($userId)
    {
        return $this->getTable()->where('user_id', $userId)->delete();
    }
}
