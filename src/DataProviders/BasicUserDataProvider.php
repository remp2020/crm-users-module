<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Random;

class BasicUserDataProvider implements UserDataProviderInterface
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public static function identifier(): string
    {
        return 'basic';
    }

    public function data($userId): ?array
    {
        $user = $this->usersRepository->find($userId);

        if (!$user || !$user->active) {
            return [];
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'created_at' => $user->created_at->getTimestamp(),
            'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->getTimestamp() : null,
            'hashed_id' => UserManager::hashedUserId($user->id),
            'locale' => $user->locale,
        ];
    }

    public function download($userId)
    {
        $user = $this->usersRepository->find($userId);

        if (!$user || !$user->active) {
            return [];
        }

        return [
            'email' => $user->email,
            'public_name' => $user->public_name,
            'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339), //confirmed_at?
            'current_sign_in_up' => $user->current_sign_in_ip, //?
        ];
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
        $user = $this->usersRepository->find($userId);
        $now = new DateTime();
        $GDPRTemplateUser = [
            // anonymize
            'email' => 'GDPR_removal@' . $now->getTimestamp() . Random::generate(),
            'first_name' => 'GDPR Removal',
            'last_name' => 'GDPR Removal',
            'public_name' => 'GDPR Removal',
            'password' => 'GDPR Removal',
            'ext_id' => null,
            'current_sign_in_ip' => 'GDPR Removal',
            'referer' => 'GDPR Removal',
            'institution_name' => 'GDPR Removal',

            // deactivate & mark as deleted
            'active' => false,
            'deleted_at' => $now,
        ];

        $this->usersRepository->update($user, $GDPRTemplateUser);
        $this->usersRepository->markAuditLogsForDelete($user->getSignature());
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
