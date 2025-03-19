<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;

class LoginAttemptsUserDataProvider implements UserDataProviderInterface
{
    public function __construct(
        private readonly LoginAttemptsRepository $loginAttemptsRepository,
    ) {
    }

    public static function identifier(): string
    {
        return 'login_attempts';
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        $lastId = 0;
        $query = $this->loginAttemptsRepository->getTable()
            ->where('user_id = ?', $userId)
            ->order('id ASC')
            ->limit(1000);

        $data = [];
        while (true) {
            $loginAttempts = (clone $query)->where('id > ?', $lastId)->fetchAll();
            if (!count($loginAttempts)) {
                break;
            }

            foreach ($loginAttempts as $loginAttempt) {
                $lastId = $loginAttempt->id;
                $data[] = [
                    'created_at' => $loginAttempt->created_at->getTimestamp(),
                    'status' => $loginAttempt->status,
                    'source' => $loginAttempt->source,
                    'ip' => $loginAttempt->ip,
                ];
            }
        }

        return $data;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $this->loginAttemptsRepository->deleteAll($userId);
    }

    public function protect($userId): array
    {
        return [];
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
