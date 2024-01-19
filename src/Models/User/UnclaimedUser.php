<?php

namespace Crm\UsersModule\Models\User;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProviders\ClaimUserDataProviderInterface;
use Crm\UsersModule\Events\UserClaimedEvent;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Models\Auth\Access\AccessTokenNotFoundException;
use Crm\UsersModule\Models\Auth\InvalidEmailException;
use Crm\UsersModule\Models\Auth\PasswordGenerator;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UserAlreadyExistsException;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Security\Passwords;
use Nette\Utils\JsonException;
use Ramsey\Uuid\Uuid;

class UnclaimedUser
{
    const META_KEY = 'unclaimed_user';

    const CLAIMED_BY_KEY = 'claimed_by';

    const CLAIMED_UNCLAIMED_USER_KEY = 'claimed_unclaimed_user';

    private $userManager;

    private $userMetaRepository;

    private $dataProviderManager;

    private $accessTokensRepository;

    private $emitter;

    private $usersRepository;

    private $userData;

    private Passwords $passwords;
    private PasswordGenerator $passwordGenerator;
    private AccessToken $accessToken;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        DataProviderManager $dataProviderManager,
        Emitter $emitter,
        UserManager $userManager,
        UserMetaRepository $userMetaRepository,
        UsersRepository $usersRepository,
        UserData $userData,
        Passwords $passwords,
        PasswordGenerator $passwordGenerator,
        AccessToken $accessToken
    ) {
        $this->userManager = $userManager;
        $this->userMetaRepository = $userMetaRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
        $this->usersRepository = $usersRepository;
        $this->userData = $userData;
        $this->passwords = $passwords;
        $this->passwordGenerator = $passwordGenerator;
        $this->accessToken = $accessToken;
    }

    /**
     * @param string|null $email
     * @param string $source
     * @param string|null $locale
     * @return ActiveRow
     * @throws InvalidEmailException
     * @throws JsonException
     * @throws UserAlreadyExistsException
     */
    public function createUnclaimedUser(string $email = null, string $source = 'unknown', string $locale = null): ActiveRow
    {
        if ($email === null) {
            $email = $this->generateUnclaimedUserEmail();
        }

        return $this->userManager->addNewUser(
            $email,
            false,
            $source,
            null,
            false,
            null,
            false,
            [self::META_KEY => 1],
            false,
            $locale
        );
    }

    /**
     * @param ActiveRow $user
     * @return bool
     */
    public function isUnclaimedUser(ActiveRow $user): bool
    {
        $meta = $this->userMetaRepository->userMetaValueByKey($user, self::META_KEY);
        if ($meta) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function generateUnclaimedUserEmail(): string
    {
        $uuid = Uuid::uuid4()->toString();
        return $uuid . '@unclaimed';
    }

    public function claimUser(ActiveRow $unclaimedUser, ActiveRow $loggedUser, ?ActiveRow $deviceToken = null): void
    {
        if (!$this->isUnclaimedUser($unclaimedUser)) {
            throw new ClaimedUserException("User {$unclaimedUser->id} is claimed");
        }
        if ($this->isUnclaimedUser($loggedUser)) {
            throw new UnclaimedUserException("User {$loggedUser->id} is unclaimed");
        }

        // Device token is not required, since unclaimed user may exist without any access token (e.g. user created via pub/sub notification)
        if ($deviceToken && !$this->accessTokensRepository->existsForUserDeviceToken($loggedUser, $deviceToken)) {
            throw new AccessTokenNotFoundException("There is no access token for user {$loggedUser->id} and device token {$deviceToken->id}");
        }

        /** @var ClaimUserDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.claim_unclaimed_user', ClaimUserDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->provide(['unclaimedUser' => $unclaimedUser, 'loggedUser' => $loggedUser]);
        }

        // deactivate unclaimed user
        if ($unclaimedUser->active) {
            $this->usersRepository->toggleActivation($unclaimedUser);
        }

        $this->userMetaRepository->add($unclaimedUser, self::CLAIMED_BY_KEY, $loggedUser->id);
        $this->userMetaRepository->add($loggedUser, self::CLAIMED_UNCLAIMED_USER_KEY, $unclaimedUser->id);

        $this->userData->refreshUserTokens($loggedUser->id);
        $this->emitter->emit(new UserClaimedEvent($unclaimedUser, $loggedUser, $deviceToken));
    }

    /**
     * getClaimer returns user, who claimed provided unclaimed user. If unclaimed user was not claimed yet, method
     * returns null.
     */
    public function getClaimer($unclaimedUser)
    {
        $claimerUserId = $this->userMetaRepository->userMetaValueByKey($unclaimedUser, self::CLAIMED_BY_KEY);
        if (!$claimerUserId) {
            return null;
        }
        return $this->usersRepository->find($claimerUserId);
    }

    public function getPreviouslyClaimedUser($user)
    {
        $claimedUserId = $this->userMetaRepository->userMetaValueByKey($user, self::CLAIMED_UNCLAIMED_USER_KEY);
        if (!$claimedUserId) {
            return null;
        }
        return $this->usersRepository->find($claimedUserId);
    }

    /**
     * Use only to register pre-registered users. Pre-registered user has valid email address.
     */
    public function makeUnclaimedUserRegistered(
        $user,
        $sendEmail = true,
        $source = null,
        $referer = null,
        $password = null
    ) {

        $this->userMetaRepository->removeMeta($user->id, self::META_KEY);

        if ($password) {
            $originalPassword = $password;
        } else {
            $originalPassword = $this->passwordGenerator->generatePassword();
        }

        $this->usersRepository->update($user, [
            'password' => $this->passwords->hash($originalPassword),
            'referer' => $referer,
            'source' => $source,
        ]);

        $this->accessToken->addUserToken($user, null, null, $source);

        $this->usersRepository->emitUserRegisteredEvents($user, $originalPassword, $sendEmail);

        return $user;
    }
}
