<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
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

    /**
     * @return array{}|array{
     *     id:int,
     *     email:string,
     *     created_at: int,
     *     confirmed_at: ?int,
     *     hashed_id: string,
     *     locale: string,
     *     uuid: string
     * }
     *     Returned element `hashed_id` is deprecated and will be removed. Use `uuid` as a unique identifier.
     */
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
            'hashed_id' => UserManager::hashedUserId($user->id), // deprecated, use `uuid` as a unique identifier
            'locale' => $user->locale,
            'uuid' => $user->uuid,
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
            'email_validated_at' => $user->email_validated_at?->format(\DateTimeInterface::RFC3339),
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'public_name' => $user->public_name,
            'institution_name' => $user->institution_name,
            'locale' => $user->locale,
            'source' => $user->source,
            'referer' => $user->referer,
            'registration_channel' => $user->registration_channel,
            'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339),
            'current_sign_in_at' => $user->current_sign_in_at?->format(\DateTimeInterface::RFC3339),
            'current_sign_in_ip' => $user->current_sign_in_ip,
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
