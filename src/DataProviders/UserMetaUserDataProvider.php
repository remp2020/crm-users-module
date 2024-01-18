<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\UserMetaRepository;

class UserMetaUserDataProvider implements UserDataProviderInterface
{
    private $userMetaRepository;

    public function __construct(UserMetaRepository $userMetaRepository)
    {
        $this->userMetaRepository = $userMetaRepository;
    }

    public static function identifier(): string
    {
        return 'user_meta';
    }

    public function data($userId): ?array
    {
        $result = [];
        foreach ($this->userMetaRepository->userMetaRows($userId)->where(['is_public' => true]) as $row) {
            $result[] = [$row->key => $row->value];
        }
        return $result;
    }

    public function download($userId)
    {
        $result = [];
        foreach ($this->userMetaRepository->userMetaRows($userId) as $row) {
            $result[] = [$row->key => $row->value];
        }
        return $result;
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
        foreach ($this->userMetaRepository->userMetaRows($userId)->where(['is_public' => true]) as $row) {
            $this->userMetaRepository->delete($row);
        }
        return false;
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
