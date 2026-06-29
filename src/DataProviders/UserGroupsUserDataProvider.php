<?php

declare(strict_types=1);

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class UserGroupsUserDataProvider implements UserDataProviderInterface
{
    public function __construct(
        private readonly UserGroupsRepository $userGroupsRepository,
        private readonly UsersRepository $usersRepository,
    ) {
    }

    public static function identifier(): string
    {
        return 'user_groups';
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
        $user = $this->usersRepository->find($userId);
        $this->userGroupsRepository->removeGroupsForUser($user);
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
