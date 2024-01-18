<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\DeviceTokenAuthorization;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class DeviceTokenAuthorizationTest extends DatabaseTestCase
{
    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    /** @var DeviceTokenAuthorization */
    private $deviceTokenAuthorization;

    /** @var UserMetaRepository */
    private $userMetaRepository;

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
        ];
    }

    public function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->userMetaRepository = $this->getRepository(UserMetaRepository::class);

        $this->deviceTokenAuthorization = $this->inject(DeviceTokenAuthorization::class);
    }

    public function testNotExistingDeviceToken()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer devtok_notexistingtoken';

        $this->assertFalse($this->deviceTokenAuthorization->authorized());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthorizedWithDeviceTokens()
    {
        $deviceToken = $this->deviceTokensRepository->generate('test_dev_id');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;

        $this->assertTrue($this->deviceTokenAuthorization->authorized());
        $this->assertEmpty($this->deviceTokenAuthorization->getAuthorizedUsers());
        $this->assertEmpty($this->deviceTokenAuthorization->getAccessTokens());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthorizedUnclaimedAndClaimedUsers()
    {
        $user1 = $this->usersRepository->add('test1@user.com', 'nbusr123');
        $this->userMetaRepository->add($user1, UnclaimedUser::META_KEY, true);
        $accessToken1 = $this->accessTokensRepository->add($user1, 3);

        $user2 = $this->usersRepository->add('test2@user.com', 'nbusr123');
        $this->userMetaRepository->add($user2, UnclaimedUser::META_KEY, true);
        $accessToken2 = $this->accessTokensRepository->add($user2, 3);

        $user3 = $this->usersRepository->add('test3@user.com', 'nbusr123');
        $accessToken3 = $this->accessTokensRepository->add($user3, 3);
        $accessToken4 = $this->accessTokensRepository->add($user3, 3);

        $deviceToken = $this->deviceTokensRepository->generate('test_dev_id');

        $this->accessTokensRepository->pairWithDeviceToken($accessToken1, $deviceToken);
        $this->accessTokensRepository->pairWithDeviceToken($accessToken2, $deviceToken);
        $this->accessTokensRepository->pairWithDeviceToken($accessToken3, $deviceToken);
        $this->accessTokensRepository->pairWithDeviceToken($accessToken4, $deviceToken);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;

        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(3, $authorizedUsers);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(4, $authorizedTokens);
    }

    public function testAuthorizedOnlyClaimedUsers()
    {
        $user1 = $this->usersRepository->add('test1@user.com', 'nbusr123');
        $accessToken1 = $this->accessTokensRepository->add($user1, 3);

        $user2 = $this->usersRepository->add('test2@user.com', 'nbusr123');
        $accessToken2 = $this->accessTokensRepository->add($user2, 3);

        $user3 = $this->usersRepository->add('test3@user.com', 'nbusr123');
        $accessToken3_1 = $this->accessTokensRepository->add($user3, 3);
        $accessToken3_2 = $this->accessTokensRepository->add($user3, 3);

        $user4 = $this->usersRepository->add('test4@user.com', 'nbusr123');
        $accessToken4 = $this->accessTokensRepository->add($user4, 3);

        $deviceToken = $this->deviceTokensRepository->generate('test_dev_id');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;

        // ================================================================================
        $this->accessTokensRepository->pairWithDeviceToken($accessToken1, $deviceToken);
        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(1, $authorizedUsers);
        $this->assertEquals($user1->id, reset($authorizedUsers)->id);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(1, $authorizedTokens);
        $this->assertEquals($accessToken1->token, reset($authorizedTokens)->token);

        // ================================================================================
        $this->accessTokensRepository->pairWithDeviceToken($accessToken2, $deviceToken);
        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(1, $authorizedUsers);
        $this->assertEquals($user2->id, reset($authorizedUsers)->id);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(1, $authorizedTokens);
        $this->assertEquals($accessToken2->token, reset($authorizedTokens)->token);

        // ================================================================================
        $this->accessTokensRepository->pairWithDeviceToken($accessToken3_1, $deviceToken);
        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(1, $authorizedUsers);
        $this->assertEquals($user3->id, reset($authorizedUsers)->id);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(1, $authorizedTokens);
        $this->assertEquals($accessToken3_1->token, reset($authorizedTokens)->token);

        // ================================================================================
        $this->accessTokensRepository->pairWithDeviceToken($accessToken3_2, $deviceToken);
        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(1, $authorizedUsers);
        $this->assertEquals($user3->id, reset($authorizedUsers)->id);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(2, $authorizedTokens);
        $this->assertEqualsCanonicalizing([$accessToken3_1->token, $accessToken3_2->token], array_column($authorizedTokens, 'token'));

        // ================================================================================
        $this->accessTokensRepository->pairWithDeviceToken($accessToken4, $deviceToken);
        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(1, $authorizedUsers);
        $this->assertEquals($user4->id, reset($authorizedUsers)->id);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(1, $authorizedTokens);
        $this->assertEquals($accessToken4->token, reset($authorizedTokens)->token);
    }
}
