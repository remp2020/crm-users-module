<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApplicationModule\EnvironmentConfig;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Email\EmailValidator;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Events\UserChangePasswordRequestEvent;
use Crm\UsersModule\Events\UserConfirmedEvent;
use Crm\UsersModule\Events\UserSuspiciousEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Security\Passwords;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class UserManager
{
    private UsersRepository $usersRepository;

    private PasswordGenerator $passwordGenerator;

    private ChangePasswordsLogsRepository $changePasswordsLogsRepository;

    private UserBuilder $userBuilder;

    private Emitter $emitter;

    private EmailValidator $emailValidator;

    private AccessTokensRepository $accessTokensRepository;

    private PasswordResetTokensRepository $passwordResetTokensRepository;

    private UserMetaRepository $userMetaRepository;

    /** @var Passwords */
    private $passwords;

    public function __construct(
        UsersRepository $usersRepository,
        PasswordGenerator $passwordGenerator,
        Emitter $emitter,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        UserBuilder $userBuilder,
        EmailValidator $emailValidator,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        AccessTokensRepository $accessTokensRepository,
        UserMetaRepository $userMetaRepository,
        Passwords $passwords
    ) {
        $this->usersRepository = $usersRepository;
        $this->passwordGenerator = $passwordGenerator;
        $this->emitter = $emitter;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->userBuilder = $userBuilder;
        $this->emailValidator = $emailValidator;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->userMetaRepository = $userMetaRepository;
        $this->passwords = $passwords;
    }

    /**
     * @return ActiveRow|bool
     * @throws InvalidEmailException
     * @throws UserAlreadyExistsException
     * @throws JsonException
     */
    public function addNewUser(
        string $email,
        bool $sendEmail = true,
        string $source = 'unknown',
        ?string $referer = null,
        bool $checkEmail = true,
        ?string $password = null,
        bool $addToken = true,
        array $userMeta = [],
        bool $emitUserRegisteredEvent = true,
        string $locale = null
    ) {
        if ($checkEmail && !$this->emailValidator->isValid($email)) {
            throw new InvalidEmailException($email);
        }

        $password = $password ?: $this->passwordGenerator->generatePassword();

        $user = $this->usersRepository->getByEmail($email);
        if ($user) {
            throw new UserAlreadyExistsException("Cannot create user, user with given email already exists: " . $email);
        }

        try {
            /** @var UserBuilder $builder */
            $builder = $this->userBuilder->createNew()
                ->sendEmail($sendEmail)
                ->setEmail($email)
                ->setPassword($password)
                ->setPublicName($email)
                ->setReferer($referer)
                ->setSource($source)
                ->setAddTokenOption($addToken)
                ->setUserMeta($userMeta)
                ->setEmitUserRegisteredEvent($emitUserRegisteredEvent);

            if ($locale) {
                $builder->setLocale($locale);
            }

            /** @var ActiveRow|bool $user */
            $user = $builder->save();
        } catch (UniqueConstraintViolationException $e) {
            throw new UserAlreadyExistsException("Cannot create user, unique constraint triggered: " . $e->getMessage());
        }

        if (!$user) {
            throw new \Exception("Cannot create user '{$email}' due to following errors: " . Json::encode($this->userBuilder->getErrors()));
        }

        return $user;
    }

    public function loadUserByEmail($email)
    {
        return $this->usersRepository->getByEmail($email);
    }

    public function loadUser(User $user)
    {
        return $this->usersRepository->find($user->getIdentity()->getId());
    }

    public function setNewPassword($userId, $actualPassword, $newPassword)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            return false;
        }

        $newPassword = trim($newPassword);

        if (!$this->passwords->verify($actualPassword, $user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            return false;
        }

        $hashedPassword = $this->passwords->hash($newPassword);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_CHANGE,
            $user->password,
            $hashedPassword
        );

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->emitter->emit(new UserChangePasswordEvent($user, $newPassword));

        return true;
    }

    public function setPublicName(ActiveRow $user, string $publicName)
    {
        $this->usersRepository->update($user, ['public_name' => $publicName]);
    }

    public function resetPassword(ActiveRow $user, $password = null, $notify = true)
    {
        $oldPassword = $user->password;

        if (!$password) {
            $password = $this->passwordGenerator->generatePassword();
        }
        $hashedPassword = $this->passwords->hash($password);

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_RESET,
            $oldPassword,
            $hashedPassword
        );

        $this->emitter->emit(new UserChangePasswordEvent($user, $password, $notify));
        return $password;
    }


    /**
     * Log out user on all devices
     *
     * @param ActiveRow $user
     * @param array $exceptTokens access_tokens you want to exclude
     *
     * @return bool if user was logged out on at least one device
     */
    public function logoutUser(ActiveRow $user, array $exceptTokens = []): bool
    {
        return $this->accessTokensRepository->removeAllUserTokens($user->id, $exceptTokens) > 0;
    }

    public function suspiciousUser(ActiveRow $user)
    {
        $oldPassword = $user->password;

        $password = $this->passwordGenerator->generatePassword();
        $hashedPassword = $this->passwords->hash($password);

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_SUSPICIOUS,
            $oldPassword,
            $hashedPassword
        );

        $this->accessTokensRepository->removeAllUserTokens($user->id);

        // do not notify about password change, suspicous event should send its own mail
        $this->emitter->emit(new UserChangePasswordEvent($user, $password, false));
        $this->emitter->emit(new UserSuspiciousEvent($user, $password));

        return true;
    }

    public function requestResetPassword($email, $source = null)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }

        $passwordResetToken = $this->passwordResetTokensRepository->add($user, $source);

        $this->emitter->emit(new UserChangePasswordRequestEvent($user, $passwordResetToken->token));

        return true;
    }

    public function confirmUser(ActiveRow $user, ?DateTime $date = null, $byAdmin = false)
    {
        if ($user->confirmed_at) {
            return;
        }

        if (!$date) {
            $date = new DateTime();
        }

        $this->usersRepository->update($user, ['confirmed_at' => $date]);
        $this->emitter->emit(new UserConfirmedEvent($user, $byAdmin));
        if ($byAdmin) {
            $this->userMetaRepository->add($user, 'confirmed_by_admin', true);
        }
    }

    public function setEmailValidated($user, ?DateTime $validated)
    {
        if ($validated) {
            $this->usersRepository->setEmailValidated($user, $validated);
        } else {
            $this->usersRepository->setEmailInvalidated($user);
        }
    }

    /**
     * Generates hashed ID derived from actual user ID.
     * Hash is generated using one-way MAC function with crmKey as the salt
     *
     * @param $userId
     *
     * @return string
     */
    public static function hashedUserId($userId): string
    {
        $crmKey = EnvironmentConfig::getCrmKey();
        if (!$crmKey) {
            throw new \Exception("Unable to generate hashed user ID using empty 'CRM_KEY' value, please set it up in .env file using 'application:generate_key' command.");
        }
        return hash_hmac('sha256', $userId, $crmKey);
    }
}
