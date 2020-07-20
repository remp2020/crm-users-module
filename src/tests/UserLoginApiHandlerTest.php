<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Api\UsersLoginHandler;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Repositories\DeviceAccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;

class UserLoginApiHandlerTest extends DatabaseTestCase
{
    const LOGIN = '1test@user.st';
    const PASSWORD = 'password';

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

    /** @var AuthenticatorManagerInterface */
    private $authenticatorManager;

    private $emitter;

    private $user;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            DeviceTokensRepository::class,
            DeviceAccessTokensRepository::class,
            AccessTokensRepository::class,
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

        $this->handler = $this->inject(UsersLoginHandler::class);

        $this->emitter = $this->inject(Emitter::class);
        $this->emitter->addListener(
            \Crm\UsersModule\Events\UserSignInEvent::class,
            $this->inject(\Crm\UsersModule\Events\SignEventHandler::class)
        );

        $this->authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $this->authenticatorManager->registerAuthenticator($this->inject(UsersAuthenticator::class));
    }

    public function testNotExistingUser()
    {
        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(400, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('no_email', $payload['error']);
    }

    public function testLoginUser()
    {
        $user = $this->getUser();

        $_POST['email'] = self::LOGIN;
        $_POST['password'] = self::PASSWORD;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(200, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('user', $payload);

        unset($_POST['email'], $_POST['password']);
    }

    public function testPairAccessAndDeviceTokens()
    {
        $user = $this->getUser();

        $deviceToken = $this->deviceTokensRepository->add('poiqwe123');

        $_POST['email'] = self::LOGIN;
        $_POST['password'] = self::PASSWORD;
        $_POST['device_token'] = $deviceToken->token;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(200, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('access', $payload);
        $this->assertArrayHasKey('token', $payload['access']);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);

        $pair = $this->deviceAccessTokensRepository->getTable()
            ->where('access_token_id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);

        unset($_POST['email'], $_POST['password'], $_POST['device_token']);
    }

    private function getUser()
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->add(self::LOGIN, self::PASSWORD, '', '');
        }
        return $this->user;
    }
}
