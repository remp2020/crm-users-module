<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repository\LoginAttemptsRepository;

class LoginAttemptsUserDataProvider implements UserDataProviderInterface
{
    private $loginAttemptsRepository;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository)
    {
        $this->loginAttemptsRepository = $loginAttemptsRepository;
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
        return [];
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
