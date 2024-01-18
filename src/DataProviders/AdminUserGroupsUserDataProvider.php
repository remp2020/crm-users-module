<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\AdminUserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class AdminUserGroupsUserDataProvider implements UserDataProviderInterface
{
    private $adminUserGroupsRepository;

    private $usersRepository;

    public function __construct(
        AdminUserGroupsRepository $adminUserGroupsRepository,
        UsersRepository $usersRepository
    ) {
        $this->adminUserGroupsRepository = $adminUserGroupsRepository;
        $this->usersRepository = $usersRepository;
    }

    public static function identifier(): string
    {
        return 'admin_user_groups';
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
        $this->adminUserGroupsRepository->removeGroupsForUser($user);
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
