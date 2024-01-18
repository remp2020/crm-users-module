<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Api\UsersLogoutHandler;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\Auth\UserTokenAuthorization;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersLogoutHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private UsersLogoutHandler $logoutHandler;
    private GetDeviceTokenApiHandler $getDeviceTokenApiHandler;
    private AccessTokensRepository $accessTokenRepository;
    private DeviceTokensRepository $deviceTokensRepository;
    private UserManager $userManager;

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            AccessTokensRepository::class,
            UsersRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoutHandler = $this->inject(UsersLogoutHandler::class);
        $this->getDeviceTokenApiHandler = $this->inject(GetDeviceTokenApiHandler::class);
        $this->accessTokenRepository = $this->getRepository(AccessTokensRepository::class);
        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testUserLogout()
    {
        $user1 = $this->addUser(UsersSeeder::USER_CUSTOMER, 'password');
        $accessToken1 = $this->accessTokenRepository->add($user1, 3);
        $accessToken2 = $this->accessTokenRepository->add($user1, 3);
        $this->assertEquals(2, $this->accessTokenRepository->all()->count());

        $this->logoutHandler->setAuthorization(new TestUserTokenAuthorization($accessToken1, $user1));
        $response = $this->runJsonApi($this->logoutHandler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        // Check that after successful logout, only one access_token is kept
        $this->assertEquals(1, $this->accessTokenRepository->all()->count());
        $storedToken = $this->accessTokenRepository->all(1)->fetch();
        $this->assertEquals($accessToken2->token, $storedToken->token);
    }

    public function testDeviceTokenLogout()
    {
        $user1 = $this->addUser('user1@crm.press', 'password');
        $userUnclaimed = $this->inject(UnclaimedUser::class)->createUnclaimedUser();
        $user2 = $this->addUser('user2@crm.press', 'password');

        $deviceToken = $this->deviceTokensRepository->generate('test');

        // Pair 2 users with device_token (1 has to be unclaimed since device token can be paired only to single standard user)
        $accessToken1 = $this->accessTokenRepository->add($user1, 3);
        $this->accessTokenRepository->pairWithDeviceToken($accessToken1, $deviceToken);

        $accessTokenUnclaimed = $this->accessTokenRepository->add($userUnclaimed, 3);
        $this->accessTokenRepository->pairWithDeviceToken($accessTokenUnclaimed, $deviceToken);

        $accessToken2 = $this->accessTokenRepository->add($user2, 3);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;
        $this->logoutHandler->setAuthorization($this->getUserTokenAuthorization());
        $response = $this->runJsonApi($this->logoutHandler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        // Check that only single token is kept
        $this->assertEquals(1, $this->accessTokenRepository->all()->count());
        $this->assertEquals($accessToken2->token, $this->accessTokenRepository->all(1)->fetch()->token);
    }

    private function getUserTokenAuthorization()
    {
        /** @var UserTokenAuthorization $userTokenAuthorization */
        $userTokenAuthorization = $this->inject(UserTokenAuthorization::class);
        $userTokenAuthorization->authorized();
        return $userTokenAuthorization;
    }

    private function addUser($email, $password)
    {
        return $this->userManager->addNewUser($email, false, 'test', null, false, $password, false);
    }
}
