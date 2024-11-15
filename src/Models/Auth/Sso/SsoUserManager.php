<?php

namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\AdminModule\Helpers\SecuredAdminAccess;
use Crm\UsersModule\Models\Auth\PasswordGenerator;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
//use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class SsoUserManager
{
    public function __construct(
        private readonly PasswordGenerator $passwordGenerator,
        private readonly UserBuilder $userBuilder,
        private readonly UserConnectedAccountsRepository $connectedAccountsRepository,
        private readonly UsersRepository $usersRepository,
        private readonly SecuredAdminAccess $securedAdminAccess,
        private readonly UnclaimedUser $unclaimedUser,
    ) {
    }

    /**
     * @throws AlreadyLinkedAccountSsoException
     * @throws AdminAccountSsoLinkingException
     */
    public function matchOrCreateUser(
        string $externalId,
        string $email,
        string $type,
        UserBuilder $userBuilder,
        $connectedAccountMeta = null,
        $loggedUserId = null
    ): ActiveRow {
        // Transaction may cause hermes event handlers to fail occasionally because the data is not committed into db at the time of event handling.
        // TODO: Return transaction with remp/crm#2218 implementation
//        $this->connectedAccountsRepository->getTransaction()->start();
//        try {
        if ($loggedUserId) {
            $connectedAccount = $this->connectedAccountsRepository->getByExternalId($type, $externalId);
            if ($connectedAccount && $connectedAccount->user->id !== $loggedUserId) {
                throw new AlreadyLinkedAccountSsoException($externalId, $email);
            }

            $user = $this->usersRepository->find($loggedUserId);
        } else {
            $user = $this->matchUser($type, $externalId, $email);

            if (!$user) {
                // if user is not in our DB, create him/her
                // our access_token is not automatically created
                $user = $userBuilder->save();
                if (!$user) {
                    throw new \RuntimeException("Unable to create users, errors: [" . implode(", ", $userBuilder->getErrors()) . "]");
                }
            }

            // if the user existed but wasn't claimed yet, claim them, so they can make a valid login
            if ($this->unclaimedUser->isUnclaimedUser($user)) {
                $this->unclaimedUser->makeUnclaimedUserRegistered(user: $user);
            }
        }

        $connectedAccount = $this->connectedAccountsRepository->getForUser($user, $type)
            ->where('external_id', $externalId)
            ->fetch();

        if (!$connectedAccount) {
            if (!$this->securedAdminAccess->canLinkOrUnlinkAccount($user)) {
                throw new AdminAccountSsoLinkingException("Unable to link user [{$user->id}]");
            }
            $this->connectedAccountsRepository->add(
                user: $user,
                type: $type,
                externalId: $externalId,
                email: $email,
                meta: $connectedAccountMeta,
            );
        }
//        } catch (\Exception $e) {
//            $this->connectedAccountsRepository->getTransaction()->rollback();
//            throw $e;
//        }
//        $this->connectedAccountsRepository->getTransaction()->commit();

        return $user;
    }
    
    public function createUserBuilder(string $email, ?string $source = null, ?string $registrationChannel = null, ?string $referer = null): UserBuilder
    {
        return $this->userBuilder->createNew()
            ->setEmail($email)
            ->setPasswordLazy(fn() => $this->passwordGenerator->generatePassword())
            ->setPublicName($email)
            ->setRole('user')
            ->setActive(true)
            ->setIsInstitution(false)
            ->setSource($source)
            ->setReferer($referer)
            ->setRegistrationChannel($registrationChannel ?? UsersRepository::DEFAULT_REGISTRATION_CHANNEL)
            ->setAddTokenOption(false);
    }

    /**
     * Hard matching is done using $externalId (in 'user_connected_accounts' table).
     * $mail is a backup, it matches account even if no connected account exists (via users.email column).
     *
     * @param string      $connectedAccountType
     * @param string      $externalId
     * @param string|null $email
     *
     * @return ActiveRow|null
     */
    public function matchUser(string $connectedAccountType, string $externalId, ?string $email = null): ?ActiveRow
    {
        // external ID
        $connectedAccount = $this->connectedAccountsRepository->getByExternalId($connectedAccountType, $externalId);
        if ($connectedAccount) {
            return $connectedAccount->user;
        }

        // email
        if ($email) {
            return $this->usersRepository->getByEmail($email) ?: null;
        }
        return null;
    }
}
