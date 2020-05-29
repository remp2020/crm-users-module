<?php

namespace Crm\UsersModule\Auth;

use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Email\EmailValidator;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Events\UserChangePasswordRequestEvent;
use Crm\UsersModule\Events\UserConfirmedEvent;
use Crm\UsersModule\Events\UserSuspiciousEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Security\Passwords;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class UserManager
{
    private $usersRepository;

    private $passwordGenerator;

    private $changePasswordsLogsRepository;

    private $userBuilder;

    private $emitter;

    private $emailValidator;

    private $addressesRepository;

    private $accessTokensRepository;

    private $passwordResetTokensRepository;

    public function __construct(
        UsersRepository $usersRepository,
        PasswordGenerator $passwordGenerator,
        Emitter $emitter,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        UserBuilder $userBuilder,
        EmailValidator $emailValidator,
        AddressesRepository $addressesRepository,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        AccessTokensRepository $accessTokensRepository
    ) {
        $this->usersRepository = $usersRepository;
        $this->passwordGenerator = $passwordGenerator;
        $this->emitter = $emitter;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->userBuilder = $userBuilder;
        $this->emailValidator = $emailValidator;
        $this->addressesRepository = $addressesRepository;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->accessTokensRepository = $accessTokensRepository;
    }

    /**
     * @param string $email
     * @param bool $sendEmail
     * @param string $source
     * @param null $referer
     * @param bool $checkEmail
     * @param string $password
     * @param bool $addToken
     *
     * @return @var ActiveRow|bool $user
     * @throws InvalidEmailException
     * @throws UserAlreadyExistsException
     * @throws \Nette\Utils\JsonException
     */
    public function addNewUser($email, $sendEmail = true, $source = 'unknown', $referer = null, $checkEmail = true, $password = null, $addToken = true)
    {
        if ($checkEmail && !$this->emailValidator->isValid($email)) {
            throw new InvalidEmailException($email);
        }

        $password = $password ?: $this->passwordGenerator->generatePassword();

        $user = $this->usersRepository->getByEmail($email);
        if ($user) {
            throw new UserAlreadyExistsException("Cannot create user, user with given email already exists: " . $email);
        }

        try {
            /** @var ActiveRow|bool $user */
            $user = $this->userBuilder->createNew()
                ->sendEmail($sendEmail)
                ->setEmail($email)
                ->setPassword($password)
                ->setPublicName($email)
                ->setReferer($referer)
                ->setSource($source)
                ->setAddTokenOption($addToken)
                ->save();
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

        if (!Passwords::verify($actualPassword, $user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            return false;
        }

        $newPassword = Passwords::hash($newPassword);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_CHANGE,
            $user->password,
            $newPassword
        );

        $this->usersRepository->update($user, [
            'password' => $newPassword,
        ]);

        $this->emitter->emit(new UserChangePasswordEvent($user));

        return true;
    }

    public function setPublicName(IRow $user, string $publicName)
    {
        $this->usersRepository->update($user, ['public_name' => $publicName]);
    }

    public function resetPassword(IRow $user, $password = null, $notify = true)
    {
        $oldPassword = $user->password;

        if (!$password) {
            $password = $this->passwordGenerator->generatePassword();
        }
        $hashedPassword = Passwords::hash($password);

        $this->usersRepository->update($user, [
            'password' => $hashedPassword,
        ]);

        $this->changePasswordsLogsRepository->add(
            $user,
            ChangePasswordsLogsRepository::TYPE_RESET,
            $oldPassword,
            $hashedPassword
        );

        $this->emitter->emit(new UserChangePasswordEvent($user, $notify));
        return $password;
    }


    /**
     * Log out user on all devices
     *
     * @param IRow  $user
     * @param array $exceptTokens access_tokens you want to exclude
     *
     * @return bool if user was logged out on at least one device
     */
    public function logoutUser(IRow $user, array $exceptTokens = []): bool
    {
        return $this->accessTokensRepository->removeAllUserTokens($user->id, $exceptTokens) > 0;
    }

    public function suspiciousUser(IRow $user)
    {
        $oldPassword = $user->password;

        $password = $this->passwordGenerator->generatePassword();
        $hashedPassword = Passwords::hash($password);

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

        $this->emitter->emit(new UserSuspiciousEvent($user, $password));

        return true;
    }

    public function requestResetPassword($email)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }

        $passwordResetToken = $this->passwordResetTokensRepository->add($user);

        $this->emitter->emit(new UserChangePasswordRequestEvent($user, $passwordResetToken->token));

        return true;
    }

    public function confirmUser(IRow $user, ?DateTime $date = null)
    {
        if (!$date) {
            $date = new DateTime();
        }
        if (!$user->confirmed_at) {
            $this->usersRepository->update($user, [
                'modified_at' => $date,
                'confirmed_at' => $date,
            ]);

            $this->emitter->emit(new UserConfirmedEvent($user));
        }
    }
}
