<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Event\LazyEventEmitter;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersEmailHandler;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Events\LoginAttemptHandler;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\IResponse;
use Nette\Utils\Random;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersEmailHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private UsersEmailHandler $handler;
    private AuthenticatorManagerInterface $authenticatorManager;
    private LoginAttemptsRepository $loginAttemptsRepository;
    private UserManager $userManager;
    private UnclaimedUser $unclaimedUser;
    private LazyEventEmitter $lazyEventEmitter;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            LoginAttemptsRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(UsersEmailHandler::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->loginAttemptsRepository = $this->getRepository(LoginAttemptsRepository::class);

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->lazyEventEmitter->addListener(
            LoginAttemptEvent::class,
            $this->inject(LoginAttemptHandler::class)
        );

        $this->authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $this->authenticatorManager->registerAuthenticator($this->inject(UsersAuthenticator::class));
    }

    protected function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(LoginAttemptEvent::class);

        parent::tearDown();
    }

    public function testNoEmail()
    {
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_missing', $payload['code']);
    }

    public function testInvalidEmail()
    {
        $_POST = [
            'email' =>'0test@user',
        ];
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('invalid_email', $payload['code']);
    }

    public function testValidEmailNoUser()
    {
        $email = 'example@example.com';

        $_POST = [
            'email' => $email,
        ];
        $response = $this->runJsonApi($this->handler);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals(null, $payload['id']);
        $this->assertEquals(null, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_NOT_FOUND_EMAIL, $lastAttempt->status);
    }

    public function testClaimedUserNoPassword()
    {
        $email = UsersSeeder::USER_CUSTOMER;

        $_POST = [
            'email' => $email,
        ];
        $response = $this->runJsonApi($this->handler);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(null, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_WRONG_PASS, $lastAttempt->status);
    }

    public function testClaimedUserInvalidPassword()
    {
        $email = UsersSeeder::USER_CUSTOMER;

        $_POST = [
            'email' => $email,
            'password' => 'invalid',
        ];
        $response = $this->runJsonApi($this->handler);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(false, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_WRONG_PASS, $lastAttempt->status);
    }

    public function testClaimedUserCorrectPassword()
    {
        $email = UsersSeeder::USER_CUSTOMER;

        $_POST = [
            'email' => $email,
            'password' => 'password',
        ];
        $response = $this->runJsonApi($this->handler);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(true, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_OK, $lastAttempt->status);
    }

    public function testUnclaimedUser()
    {
        $email = 'unclaimed@unclaimed.sk';
        $this->unclaimedUser->createUnclaimedUser($email);

        $_POST = [
            'email' => $email,
        ];
        $response = $this->runJsonApi($this->handler);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertNull($payload['id']);
        $this->assertNull($payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_UNCLAIMED_USER, $lastAttempt->status);
    }

    public function testEmailTooLong()
    {
        $email = Random::generate('255') . '@example.com';

        $_POST = [
            'email' => $email,
        ];
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S422_UNPROCESSABLE_ENTITY, $response->getCode());
    }


    private function lastLoginAttempt()
    {
        return $this->loginAttemptsRepository->getTable()
            ->order('created_at DESC')
            ->limit(1)
            ->fetch();
    }
}
