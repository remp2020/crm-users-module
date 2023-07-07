<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\ApplicationModule\User\RedisUserDataStorage;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\UsersModule\Api\UsersTouchApiHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\BasicUserDataProvider;
use Crm\UsersModule\User\UserData;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersTouchApiHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private UsersTouchApiHandler $usersTouchApiHandler;

    private UserManager $userManager;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
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

        $this->userManager = $this->inject(UserManager::class);

        /** @var AccessTokensRepository $accessTokensRepository */
        $accessTokensRepository = $this->inject(AccessTokensRepository::class);

        $apiUser = $this->userManager->addNewUser('admin@example.com');
        $apiAccessToken = $accessTokensRepository->add($apiUser);

        $this->usersTouchApiHandler = $this->inject(UsersTouchApiHandler::class);
        $this->usersTouchApiHandler->setAuthorization(new TestUserTokenAuthorization($apiAccessToken, $apiUser));
    }

    public function testCallWithMissingUser(): void
    {
        $_GET['id'] = 11111;
        /** @var JsonApiResponse $response */
        $response = $this->runApi($this->usersTouchApiHandler);

        $this->assertEquals(IResponse::S404_NotFound, $response->getCode());
        $this->assertEquals('error', $response->getPayload()['status']);
    }

    public function testSuccessfulRefreshCall(): void
    {
        /** @var UserData $userData */
        $userData = $this->inject(UserData::class);

        /** @var RedisUserDataStorage $userDataStorage */
        $userDataStorage = $this->inject(RedisUserDataStorage::class);

        /** @var UserDataRegistrator $userDataRegistrator */
        $userDataRegistrator = $this->inject(UserDataRegistrator::class);
        $userDataRegistrator->addUserDataProvider($this->inject(BasicUserDataProvider::class));

        $user = $this->userManager->addNewUser('test@example.com');

        // save user's data into storage
        $userData->refreshUserTokens($user->id);

        // get stored data
        $userToken = $user->related('access_tokens')->fetch();
        $actualData = Json::decode($userDataStorage->load($userToken->token), Json::FORCE_ARRAY);

        // make changes in basic user data - change confirmed_at
        $this->userManager->confirmUser($user);

        // call API handler to touch - refresh user's data
        $_GET['id'] = $user->id;
        /** @var JsonApiResponse $response */
        $response = $this->runApi($this->usersTouchApiHandler);

        $this->assertEquals(IResponse::S200_OK, $response->getCode());
        $this->assertEquals("ok", $response->getPayload()['status']);

        // get actual data from storage
        $refreshedData = Json::decode($userDataStorage->load($userToken->token), Json::FORCE_ARRAY);

        $this->assertNotEquals($actualData['basic']['confirmed_at'], $refreshedData['basic']['confirmed_at']);
    }
}
