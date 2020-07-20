<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Repositories\DeviceAccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;

class UserCreateApiHandlerTest extends DatabaseTestCase
{
    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UsersCreateHandler */
    private $handler;

    /** @var DeviceAccessTokensRepository */
    private $deviceAccessTokensRepository;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            DeviceTokensRepository::class,
            DeviceAccessTokensRepository::class,
            AccessTokensRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->inject(DeviceTokensRepository::class);
        $this->deviceAccessTokensRepository = $this->inject(DeviceAccessTokensRepository::class);
        $this->usersRepository = $this->inject(UsersRepository::class);
        $this->accessTokensRepository = $this->inject(AccessTokensRepository::class);

        $this->handler = $this->inject(UsersCreateHandler::class);
    }

    public function testCreateUserEmailError()
    {
        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(404, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testCreateUserOnlyWithEmail()
    {
        $_POST['email'] = '0test@user.site';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 200);

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        unset($_POST['email']);
    }

    public function testCreateUserPairsDeviceAndAccessToken()
    {
        $deviceToken = $this->deviceTokensRepository->add('testdevid123');

        $_POST['email'] = '0test2@user.site';
        $_POST['device_token'] = $deviceToken->token;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 200);

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);
        $pair = $this->deviceAccessTokensRepository->getTable()
            ->where('access_token_id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);

        unset($_POST['email'], $_POST['device_token']);
    }

    public function testCreateUserNotExistingDeviceToken()
    {
        $_POST['email'] = '0test2@user.site';
        $_POST['device_token'] = 'devtok_sd8a907sas987du';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 400);

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('device_token_doesnt_exist', $payload['code']);

        unset($_POST['email'], $_POST['device_token']);
    }
}
