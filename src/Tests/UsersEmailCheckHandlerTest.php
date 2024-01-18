<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersEmailCheckHandler;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\IResponse;

class UsersEmailCheckHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private UsersRepository $usersRepository;
    private UserMetaRepository $userMetaRepository;

    private UsersEmailCheckHandler $handler;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userMetaRepository = $this->getRepository(UserMetaRepository::class);

        $this->handler = $this->inject(UsersEmailCheckHandler::class);
    }

    public function testEmailAvailable(): void
    {
        $_POST = [
            'email' => 'user@user.sk'
        ];
        $response = $this->runJsonApi($this->handler);

        self::assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        self::assertEquals('user@user.sk', $payload['email']);
        self::assertEquals(UsersEmailCheckHandler::STATUS_AVAILABLE, $payload['status']);
        self::assertArrayNotHasKey('id', $payload);
    }

    public function testEmailTaken(): void
    {
        $_POST = [
            'email' => 'user@user.sk'
        ];
        $user = $this->usersRepository->add('user@user.sk', 'password');
        $response = $this->runJsonApi($this->handler);

        self::assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        self::assertEquals('user@user.sk', $payload['email']);
        self::assertEquals(UsersEmailCheckHandler::STATUS_TAKEN, $payload['status']);
        self::assertEquals($user->id, $payload['id']);
    }

    public function testEmailUnclaimed(): void
    {
        $_POST = [
            'email' => 'user@user.sk'
        ];
        $user = $this->usersRepository->add('user@user.sk', 'password');
        $this->userMetaRepository->add($user, UnclaimedUser::META_KEY, 1);
        $response = $this->runJsonApi($this->handler);

        self::assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        self::assertEquals('user@user.sk', $payload['email']);
        self::assertEquals(UsersEmailCheckHandler::STATUS_AVAILABLE, $payload['status']);
        self::assertArrayNotHasKey('id', $payload);
    }

    public function testEmailError(): void
    {
        $email = str_pad('user@user.sk', 300, 'x', STR_PAD_LEFT);
        $_POST = [
            'email' => $email
        ];
        $response = $this->runJsonApi($this->handler);

        self::assertEquals(IResponse::S422_UnprocessableEntity, $response->getCode());

        $payload = $response->getPayload();
        self::assertEquals(UsersEmailCheckHandler::STATUS_ERROR, $payload['status']);
        self::assertEquals('Invalid email format', $payload['message']);
        self::assertEquals('invalid_email', $payload['code']);
    }

    public function testMissingEmail(): void
    {
        $_POST = [];
        $response = $this->runJsonApi($this->handler);

        self::assertEquals(IResponse::S400_BadRequest, $response->getCode());

        $payload = $response->getPayload();
        self::assertEquals(UsersEmailCheckHandler::STATUS_ERROR, $payload['status']);
        self::assertEquals('invalid_input', $payload['code']);
        self::assertEquals('Field is required', $payload['errors']['email'][0] ?? '');
    }
}
