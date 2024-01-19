<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Models\Response\EmptyResponse;
use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\ApplicationModule\Models\User\UserDataRegistrator;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\DeleteUserApiHandler;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Tomaj\NetteApi\Response\JsonApiResponse;

class DeleteUserApiHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    const EMAIL = 'testdeleteuser@example.com';

    private AccessTokensRepository $accessTokensRepository;
    private UsersRepository $usersRepository;
    private UserDataRegistrator $userDataRegistrator;
    private DeleteUserApiHandler $handler;
    private ActiveRow $user;

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userDataRegistrator = $this->inject(UserDataRegistrator::class);
        $this->handler = $this->inject(DeleteUserApiHandler::class);
    }

    public function testSuccessfulDeleteUser()
    {
        $user = $this->getUser();
        $accessToken = $this->accessTokensRepository->add($user);

        $userDataProviderMock = \Mockery::mock(UserDataProviderInterface::class);
        $userDataProviderMock->shouldReceive('canBeDeleted')->andReturn([true, null]);
        $userDataProviderMock->shouldReceive('delete')->once();
        $userDataProviderMock->shouldIgnoreMissing();

        $this->userDataRegistrator->addUserDataProvider($userDataProviderMock, 10);
        $this->handler->setAuthorization(new TestUserTokenAuthorization($accessToken, $user));
        $response = $this->runApi($this->handler);

        $this->assertEquals(EmptyResponse::class, get_class($response));
        $this->assertEquals(204, $response->getCode());
    }

    public function testDeleteProtectedUserError()
    {
        $user = $this->getUser();
        $accessToken = $this->accessTokensRepository->add($user);

        /** @var UserDataProviderInterface $userDataProviderMock */
        $userDataProviderMock = \Mockery::mock(UserDataProviderInterface::class)
            ->shouldReceive('canBeDeleted')
            ->andReturn([false, "err"])
            ->getMock();
        $this->userDataRegistrator->addUserDataProvider($userDataProviderMock, 20);

        $this->handler->setAuthorization(new TestUserTokenAuthorization($accessToken, $user));
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(403, $response->getCode());

        $userFound = $this->usersRepository->findBy('email', self::EMAIL);
        $this->assertNotEmpty($userFound);
    }

    private function getUser()
    {
        if (!isset($this->user)) {
            $this->user = $this->usersRepository->add(self::EMAIL, 'nbusr123');
        }
        return $this->user;
    }
}
