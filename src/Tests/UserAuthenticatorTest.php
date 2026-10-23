<?php

namespace Crm\UsersModule\Tests;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Authenticator\AccessTokenAuthenticator;
use Crm\UsersModule\Authenticator\AutoLoginAuthenticator;
use Crm\UsersModule\Authenticator\AutoLoginTokenAuthenticator;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Events\LoginAttemptHandler;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Models\Auth\AutoLogin\AutoLogin;
use Crm\UsersModule\Models\Auth\UserAuthenticator;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Utils\DateTime;

class UserAuthenticatorTest extends DatabaseTestCase
{
    /** @var UserAuthenticator */
    private $userAuthenticator;

    /** @var UserManager $userManager */
    private $userManager;

    /** @var UsersRepository $usersRepository */
    private $usersRepository;

    /** @var AccessTokensRepository $accessTokenRepository */
    private $accessTokenRepository;

    private LoginAttemptsRepository $loginAttemptsRepository;

    private AutoLogin $autoLogin;

    private LazyEventEmitter $lazyEventEmitter;

    /** @var string $accessTokenLastVersion */
    private $accessTokenLastVersion;

    /** @var ActiveRow|null $user */
    private $user;

    /** @var ActiveRow|null $admin */
    private $admin;

    /** @var UnclaimedUser $unclaimedUser */
    private $unclaimedUser;

    /** @var Translator */
    private $translator;

    private $testUserEmail = "test@test.test";
    private $testUserPassword = "nbusr123";
    private $testAdminEmail = "admin@test.test";
    private $testAdminPassword = "nbusr123";

    public function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class,
            AccessTokensRepository::class,
            LoginAttemptsRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();

        $authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $authenticatorManager->registerAuthenticator(
            $this->inject(AutoLoginAuthenticator::class),
            700,
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(UsersAuthenticator::class),
            500,
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(AccessTokenAuthenticator::class),
            200,
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(AutoLoginTokenAuthenticator::class),
            800,
        );

        $this->userAuthenticator = $this->inject(UserAuthenticator::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokenRepository = $this->getRepository(AccessTokensRepository::class);
        $this->loginAttemptsRepository = $this->getRepository(LoginAttemptsRepository::class);
        $this->autoLogin = $this->inject(AutoLogin::class);

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->lazyEventEmitter->addListener(
            LoginAttemptEvent::class,
            $this->inject(LoginAttemptHandler::class),
        );

        /** @var AccessToken $accessToken */
        $accessToken = $this->inject(AccessToken::class);
        $this->accessTokenLastVersion = $accessToken->lastVersion();

        $this->translator = $this->inject(Translator::class);

        $this->getUser();
        $this->getAdmin();
    }

    protected function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(LoginAttemptEvent::class);

        parent::tearDown();
    }


    /* **************************************************************
     * Username/Password API login
     * - makes sence only for code coverage (block setting $api), other scenarios are same as for username/password
     */

    public function testUsernamePasswordApi()
    {
        $userIdentity = $this->userAuthenticator->authenticateCredentials([
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
            'source' => "api.test.test",
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }


    /* **************************************************************
     * Username/Password login
     */

    public function testUsernamePassword()
    {
        $userIdentity = $this->userAuthenticator->authenticateCredentials([
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testIncorrectUsername()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($this->translator->translate('users.authenticator.identity_not_found'));
        $this->expectExceptionCode(Authenticator::IdentityNotFound);

        $this->userAuthenticator->authenticateCredentials([
            'username' => 'incorrect+' . $this->testUserEmail,
            'password' => $this->testUserPassword,
        ]);
    }

    public function testIncorrectPassword()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($this->translator->translate('users.authenticator.invalid_credentials'));
        $this->expectExceptionCode(Authenticator::InvalidCredential);

        $this->userAuthenticator->authenticateCredentials([
            'username' => $this->testUserEmail,
            'password' => 'incorrect+'.$this->testUserPassword,
        ]);
    }

    public function testInactiveUser()
    {
        $testInactiveEmail = "inactive@test.test";
        $testInactivePassword = "nbusr123";
        $this->loadUser($testInactiveEmail, $testInactivePassword, UsersRepository::ROLE_USER, false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($this->translator->translate('users.authenticator.inactive_account'));
        $this->expectExceptionCode(Authenticator::IdentityNotFound);

        $this->userAuthenticator->authenticateCredentials([
            'username' => $testInactiveEmail,
            'password' => $testInactivePassword,
        ]);
    }

    public function testUnclaimedUser()
    {
        $testUnclaimedEmail = "unclaimed@test.test";
        $testUnclaimedPassword = "nbusr123";
        $this->unclaimedUser->createUnclaimedUser($testUnclaimedEmail);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($this->translator->translate('users.authenticator.unclaimed_user'));
        $this->expectExceptionCode(Authenticator::NotApproved);

        $this->userAuthenticator->authenticateCredentials([
            'username' => $testUnclaimedEmail,
            'password' => $testUnclaimedPassword,
        ]);
    }


    /* **************************************************************
     * AutoLoginAuthenticator
     */

    public function testAutoLogin()
    {
        $userIdentity = $this->userAuthenticator->authenticateCredentials([
            'user' => $this->user,
            'autoLogin' => true,
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testAutoLoginFalseFlag()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(Authenticator::IdentityNotFound);

        $userIdentity = $this->userAuthenticator->authenticateCredentials([
            'user' => $this->user,
            'autoLogin' => false,
        ]);
    }

    /* **************************************************************
     * ACCESS TOKEN
     */

    public function testAccessToken()
    {
        $token = $this->accessTokenRepository->add($this->getUser(), $this->accessTokenLastVersion);

        $userIdentity = $this->userAuthenticator->authenticateCredentials(['accessToken' => $token->token]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testAccessTokenAdmin()
    {
        $token = $this->accessTokenRepository->add($this->getAdmin(), $this->accessTokenLastVersion);

        try {
            $this->userAuthenticator->authenticateCredentials(['accessToken' => $token->token]);
            $this->fail('Admin login with access token should be rejected.');
        } catch (AuthenticationException $e) {
            $this->assertEquals($this->translator->translate('users.authenticator.access_token.autologin_disabled'), $e->getMessage());
            $this->assertEquals(Authenticator::NotApproved, $e->getCode());
        }

        $lastAttempt = $this->lastLoginAttempt();
        $this->assertEquals(LoginAttemptsRepository::STATUS_LOGIN_TYPE_DISABLED_FOR_ADMIN, $lastAttempt->status);
        $this->assertEquals($this->getAdmin()->id, $lastAttempt->user_id);
    }

    public function testAccessTokenInvalid()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($this->translator->translate('users.authenticator.access_token.invalid_token'));
        $this->expectExceptionCode(Authenticator::Failure);

        $this->userAuthenticator->authenticateCredentials(['accessToken' => "invalid_token"]);
    }


    /* **************************************************************
     * AUTOLOGIN TOKEN
     */

    public function testAutoLoginTokenAdmin()
    {
        $autoLoginToken = $this->autoLogin->addUserToken($this->getAdmin(), DateTime::from('-1 hour'), DateTime::from('+1 hour'));

        try {
            $this->userAuthenticator->authenticateCredentials(['autologin_token' => $autoLoginToken->token]);
            $this->fail('Admin login with autologin token should be rejected.');
        } catch (AuthenticationException $e) {
            $this->assertEquals('Autologin for this account is disabled', $e->getMessage());
            $this->assertEquals(Authenticator::NotApproved, $e->getCode());
        }

        $lastAttempt = $this->lastLoginAttempt();
        $this->assertEquals(LoginAttemptsRepository::STATUS_LOGIN_TYPE_DISABLED_FOR_ADMIN, $lastAttempt->status);
        $this->assertEquals($this->getAdmin()->id, $lastAttempt->user_id);
    }

    /* **************************************************************
     * Helpers
     */

    private function lastLoginAttempt(): ActiveRow
    {
        return $this->loginAttemptsRepository->getTable()
            ->order('id DESC')
            ->limit(1)
            ->fetch();
    }

    private function getUser() : ActiveRow
    {
        if (!$this->user) {
            $this->user = $this->loadUser($this->testUserEmail, $this->testUserPassword, UsersRepository::ROLE_USER);
        }

        return $this->user;
    }

    private function getAdmin() : ActiveRow
    {
        if (!$this->admin) {
            $this->admin = $this->loadUser($this->testAdminEmail, $this->testAdminPassword, UsersRepository::ROLE_ADMIN);
        }

        return $this->admin;
    }

    private function loadUser($email, $password, $role = UsersRepository::ROLE_USER, $active = true) : ActiveRow
    {
        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $user = $this->usersRepository->add($email, $password, $role, (int)$active);
        }
        return $user;
    }
}
