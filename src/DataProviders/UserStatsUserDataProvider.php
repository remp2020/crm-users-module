<?php
declare(strict_types=1);

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\UserStatsRepository;

class UserStatsUserDataProvider implements UserDataProviderInterface
{

    public function __construct(
        private readonly UserStatsRepository $userStatsRepository,
    ) {
    }

    public static function identifier(): string
    {
        return 'user_stats';
    }

    public function data($userId): ?array
    {
        // no need to store user stats in redis
        return [];
    }

    public function download($userId)
    {
        // these are not personal data; just aggregated numbers from data that are part of export
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
        $this->userStatsRepository->deleteAll($userId);
        return false;
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
