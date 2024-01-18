<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\RegistrationAttemptsRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UserCreateApiHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private DeviceTokensRepository $deviceTokensRepository;
    private UsersRepository $usersRepository;
    private UsersCreateHandler $handler;
    private AccessTokensRepository $accessTokensRepository;
    private UnclaimedUser $unclaimedUser;
    private Emitter $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->emitter = $this->inject(Emitter::class);

        $this->handler = $this->inject(UsersCreateHandler::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            UsersRepository::class,
            AccessTokensRepository::class,
            UserMetaRepository::class,
            RegistrationAttemptsRepository::class,
        ];
    }

    public function testCreateUserEmailError()
    {
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(404, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testCreateUserOnlyWithEmail()
    {
        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $_POST = [
            'email' => '0test@user.site',
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUserPairsDeviceAndAccessToken()
    {
        $deviceToken = $this->deviceTokensRepository->generate('testdevid123');

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $_POST = [
            'email' => '0test2@user.site',
            'device_token' => $deviceToken->token,
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);
        $pair = $this->accessTokensRepository->getTable()
            ->where('id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);
        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUserNotExistingDeviceToken()
    {
        $_POST = [
            'email' => '0test2@user.site',
            'device_token' => 'devtok_sd8a907sas987du',
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('device_token_doesnt_exist', $payload['code']);
    }

    public function testCreateUnclaimedUser()
    {
        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $_POST = [
            'email' => '0test2@user.site',
            'unclaimed' => true,
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testUnclaimedUserAlreadyExists()
    {
        $email = '0test2@user.site';
        $this->unclaimedUser->createUnclaimedUser($email);

        $_POST = [
            'email' => $email,
            'unclaimed' => true,
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_taken', $payload['code']);
    }

    public function testStandardUserAlreadyExists()
    {
        $email = '0test2@user.site';
        $this->usersRepository->add($email, '123456');

        $_POST = [
            'email' => $email,
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_taken', $payload['code']);
    }

    public function testRegisterUnclaimedUser()
    {
        $email = '0test2@user.site';
        $this->unclaimedUser->createUnclaimedUser($email);

        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $_POST = [
            'email' => $email,
        ];
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }
}
