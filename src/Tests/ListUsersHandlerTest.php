<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\ListUsersHandler;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ListUsersHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private ListUsersHandler $handler;
    private UsersRepository $usersRepository;

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(ListUsersHandler::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    public function testListOnlyActiveUsers()
    {
        $activated = $this->getUser('activated@example.com', true);
        $deactivated = $this->getUser('deactivated@example.com', false);
        $anonymized = $this->getUser('deleted@example.com', false);
        $this->usersRepository->update($anonymized, ['deleted_at' => new DateTime()]);

        $_POST['user_ids'] = Json::encode([$activated->getPrimary(),$deactivated->getPrimary()]);
        $_POST['page'] = 1;

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('totalCount', $payload);
        $this->assertArrayHasKey('users', $payload);
        $this->assertEquals(1, $payload['totalCount']);
        $this->assertEquals(1, count($payload['users']));

        // check listed users
        $this->assertEquals($activated->email, $payload['users'][$activated->getPrimary()]['email']);
        $this->assertArrayNotHasKey($deactivated->getPrimary(), $payload['users']); // deactivated user is not listed
        $this->assertArrayNotHasKey($anonymized->getPrimary(), $payload['users']); // anonymized user is not listed

        unset($_POST['user_ids'], $_POST['page']);
    }

    public function testListAlsoDeactivatedUsers()
    {
        $activated = $this->getUser('activated@example.com', true);
        $deactivated = $this->getUser('deactivated@example.com', false);
        $anonymized = $this->getUser('deleted@example.com', false);
        $this->usersRepository->update($anonymized, ['deleted_at' => new DateTime()]);

        $_POST['user_ids'] = Json::encode([$activated->getPrimary(),$deactivated->getPrimary()]);
        $_POST['page'] = 1;
        $_POST['include_deactivated'] = true;

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('totalCount', $payload);
        $this->assertArrayHasKey('users', $payload);
        $this->assertEquals(2, $payload['totalCount']);
        $this->assertEquals(2, count($payload['users']));

        // check listed users
        $this->assertEquals($activated->email, $payload['users'][$activated->getPrimary()]['email']);
        $this->assertEquals($deactivated->email, $payload['users'][$deactivated->getPrimary()]['email']);
        $this->assertArrayNotHasKey($anonymized->getPrimary(), $payload['users']); // anonymized user is not listed

        unset($_POST['user_ids'], $_POST['page']);
    }

    private function getUser($email, $activated)
    {
        $user = $this->usersRepository->getByEmail($email);
        if (!$user) {
            $user = $this->usersRepository->add(
                $email,
                'password',
                UsersRepository::ROLE_USER,
                $activated,
            );
        }
        return $user;
    }
}
