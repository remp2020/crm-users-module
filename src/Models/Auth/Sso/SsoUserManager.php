<?php


namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\UsersModule\Models\Auth\PasswordGenerator;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
//use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class SsoUserManager
{
//    private Explorer $dbContext;

    private UserConnectedAccountsRepository $connectedAccountsRepository;

    private UsersRepository $usersRepository;

    private PasswordGenerator $passwordGenerator;

    private UserBuilder $userBuilder;

    public function __construct(
        PasswordGenerator $passwordGenerator,
        UserBuilder $userBuilder,
        //        Explorer $dbContext,
        UserConnectedAccountsRepository $connectedAccountsRepository,
        UsersRepository $usersRepository
    ) {
//        $this->dbContext = $dbContext;
        $this->connectedAccountsRepository = $connectedAccountsRepository;
        $this->usersRepository = $usersRepository;
        $this->passwordGenerator = $passwordGenerator;
        $this->userBuilder = $userBuilder;
    }

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
//        $this->dbContext->beginTransaction();
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
            }
        }

            $this->connectedAccountsRepository->connectUser(
                $user,
                $type,
                $externalId,
                $email,
                $connectedAccountMeta
            );
//        } catch (\Exception $e) {
//            $this->dbContext->rollBack();
//            throw $e;
//        }
//        $this->dbContext->commit();

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
