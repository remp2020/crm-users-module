<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class GetDeviceTokenApiHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private AccessTokensRepository $accessTokensRepository;

    private DeviceTokensRepository $deviceTokensRepository;

    private UsersRepository $usersRepository;

    private GetDeviceTokenApiHandler $handler;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->handler = $this->inject(GetDeviceTokenApiHandler::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_POST);
    }

    public function testGenerateDeviceToken()
    {
        $_POST['device_id'] = 'asd123';

        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('device_token', $payload);
    }

    public function testMissingDeviceIdParam()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertEquals('error', $payload['status']);
    }

    public function testWrongAccessToken()
    {
        $_POST['device_id'] = 'asd123';
        $_POST['access_token'] = '1478';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Access token not valid', $payload['message']);
    }

    public function testGenerateWithPairAccessToken()
    {
        $_POST['device_id'] = 'asd123';

        $user = $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN);
        $accessTokenRow = $this->accessTokensRepository->add($user, 1);
        $_POST['access_token'] = $accessTokenRow->token;

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('device_token', $payload);

        $accessToken = $this->accessTokensRepository->loadToken($accessTokenRow->token);
        $deviceToken = $this->deviceTokensRepository->findByToken($payload['device_token']);
        $this->assertEquals($accessToken->device_token_id, $deviceToken->id);
    }
}
