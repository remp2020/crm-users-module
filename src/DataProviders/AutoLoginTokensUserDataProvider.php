<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\AutoLoginTokensRepository;

class AutoLoginTokensUserDataProvider implements UserDataProviderInterface
{
    private $autologinTokensRepository;

    public function __construct(AutoLoginTokensRepository $autologinTokensRepository)
    {
        $this->autologinTokensRepository = $autologinTokensRepository;
    }

    public static function identifier(): string
    {
        return 'autologin_tokens';
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

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $this->autologinTokensRepository->deleteAll($userId);
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
