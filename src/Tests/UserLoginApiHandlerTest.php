<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Models\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersLoginHandler;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Events\SignEventHandler;
use Crm\UsersModule\Events\UserSignInEvent;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UserLoginApiHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    const LOGIN = '1test@user.st';
    const PASSWORD = 'password';

    private DeviceTokensRepository $deviceTokensRepository;
    private UsersRepository $usersRepository;
    private UsersLoginHandler $handler;
    private AccessTokensRepository $accessTokensRepository;
    private AuthenticatorManagerInterface $authenticatorManager;
    private UnclaimedUser $unclaimedUser;
    private LazyEventEmitter $lazyEventEmitter;

    private $user;

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class,
            AccessTokensRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->handler = $this->inject(UsersLoginHandler::class);

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->lazyEventEmitter->addListener(
            UserSignInEvent::class,
            $this->inject(SignEventHandler::class)
        );

        $this->authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $this->authenticatorManager->registerAuthenticator($this->inject(UsersAuthenticator::class));
    }

    protected function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(UserSignInEvent::class);

        parent::tearDown();
    }

    public function testNotExistingUser()
    {
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(400, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('no_email', $payload['error']);
    }

    public function testUnclaimedUser()
    {
        $this->unclaimedUser->createUnclaimedUser(self::LOGIN);

        $_POST = [
            'email' => self::LOGIN,
            'password' => self::PASSWORD,
        ];
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(400, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('auth_failed', $payload['error']);
    }

    public function testLoginUser()
    {
        $user = $this->getUser();

        $_POST = [
            'email' => self::LOGIN,
            'password' => self::PASSWORD,
        ];
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('user', $payload);
    }

    public function testPairAccessAndDeviceTokens()
    {
        $this->getUser();

        $deviceToken = $this->deviceTokensRepository->generate('poiqwe123');

        $_POST = [
            'email' => self::LOGIN,
            'password' => self::PASSWORD,
            'device_token' => $deviceToken->token,
        ];
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('access', $payload);
        $this->assertArrayHasKey('token', $payload['access']);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);

        $pair = $this->accessTokensRepository->getTable()
            ->where('id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);
    }

    private function getUser()
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->add(self::LOGIN, self::PASSWORD);
        }
        return $this->user;
    }
}
