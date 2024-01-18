<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Crm\UsersModule\Repository\UsersRepository;

class UserConnectedAccountsDataProvider implements UserDataProviderInterface
{
    private $usersRepository;

    private $userConnectedAccountsRepository;

    public function __construct(
        UserConnectedAccountsRepository $userConnectedAccountsRepository,
        UsersRepository $usersRepository
    ) {
        $this->usersRepository = $usersRepository;
        $this->userConnectedAccountsRepository = $userConnectedAccountsRepository;
    }

    public static function identifier(): string
    {
        return 'user_connected_accounts';
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
        $this->userConnectedAccountsRepository->removeAccountsForUser($user);
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
