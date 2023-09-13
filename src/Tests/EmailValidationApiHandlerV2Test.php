<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\v2\EmailValidationApiHandler;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;

class EmailValidationApiHandlerV2Test extends DatabaseTestCase
{
    use ApiTestTrait;

    private UsersRepository $usersRepository;
    private UserMetaRepository $userMetaRepository;
    private UnclaimedUser $unclaimedUser;
    private EmailValidationApiHandler $handler;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userMetaRepository = $this->inject(UserMetaRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->handler = $this->inject(EmailValidationApiHandler::class);
    }

    public function testSetEmailValidatedExistingUser()
    {
        $users = [$this->getUser('test@example.com'), $this->getUser('another_test@example.com')];
        $payload = Json::encode(['emails' => ['test@example.com', 'another_test@example.com']]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode(), Json::encode($response->getPayload()));

        $user = $this->usersRepository->find($users[0]->id);
        $this->assertNotNull($user->email_validated_at);
        $user = $this->usersRepository->find($users[1]->id);
        $this->assertNotNull($user->email_validated_at);

        // invalidate right away, sunny day scenario

        $this->handler->setAction(EmailValidationApiHandler::INVALIDATE);
        $this->handler->setRawPayload($payload);
        $response = $this->runJsonApi($this->handler);

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($users[0]->id);
        $this->assertNull($user->email_validated_at);
        $user = $this->usersRepository->find($users[1]->id);
        $this->assertNull($user->email_validated_at);
    }

    public function testSetEmailValidatedInvalidUser()
    {
        $payload = Json::encode(['emails' => ['test@example.com', 'another_test@example.com']]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        // TODO: Test is here so if the api ever starts returning errors nobody forgets to add tests.
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('ok', $response->getPayload()['status']);
    }

    public function testSetEmailValidatedNoEmail()
    {
        $payload = Json::encode(['emails' => []]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('ok', $response->getPayload()['status']);
    }

    public function testSetEmailValidatedNoEmailField()
    {
        $payload = Json::encode(['wrong field' => ['test@example.com', 'another_test@example.com']]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(400, $response->getCode());
    }

    public function testSetEmailValidatedInvalidEmail()
    {
        $payload = Json::encode(['emails' => ['not an email']]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        // TODO: Test is here so if the api ever starts returning errors nobody forgets to add tests.
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('ok', $response->getPayload()['status']);
    }

    public function testUnclaimedUser()
    {
        $email = 'unclaimed@unclaimed.sk';
        $this->unclaimedUser->createUnclaimedUser($email);
        $payload = Json::encode(['emails' => [$email]]);
        $this->handler->setRawPayload($payload);

        $this->handler->setAction(EmailValidationApiHandler::VALIDATE);
        $response = $this->runJsonApi($this->handler);

        // TODO: Test is here so if the api ever starts returning errors nobody forgets to add tests.
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('ok', $response->getPayload()['status']);
    }

    private function getUser($email)
    {
        return $this->usersRepository->add($email, 'secret');
    }
}
