<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Models\Auth\Access\AccessTokenNotFoundException;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\ClaimedUserException;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Models\User\UnclaimedUserException;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use League\Event\AbstractListener;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;

class UnclaimedUserTest extends DatabaseTestCase
{
    /** @var UnclaimedUser */
    private $unclaimedUser;

    /** @var Emitter */
    private $emitter;

    private $unclaimedUserObj;

    private $loggedUser;

    private $deviceToken;

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $userManager = $this->inject(UserManager::class);
        $this->emitter = $this->inject(Emitter::class);

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $userManager->addNewUser('example@example.com');
        $this->deviceToken = $deviceTokensRepository->add('test_device_id', 'test_device_token');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    public function testCreateUnclaimedUserWithoutEmail()
    {
        $user = $this->unclaimedUser->createUnclaimedUser();

        $this->assertIsObject($user);
        $this->assertInstanceOf(ActiveRow::class, $user);
        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUnclaimedUserWithEmail()
    {
        $email = 'unclaimed@user.com';

        $newUserEventListener = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $newUserEventListener);
        $userRegisteredEventListener = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $userRegisteredEventListener);

        $user = $this->unclaimedUser->createUnclaimedUser($email);

        $this->assertIsObject($user);
        $this->assertInstanceOf(ActiveRow::class, $user);
        $this->assertEquals($email, $user->email);
        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testClaimUserClaimedUserException()
    {
        $this->expectException(ClaimedUserException::class);
        $this->unclaimedUser->claimUser($this->loggedUser, $this->unclaimedUserObj, $this->deviceToken);
    }

    public function testClaimUserUnclaimedUserException()
    {
        $this->expectException(UnclaimedUserException::class);
        $this->unclaimedUser->claimUser($this->unclaimedUserObj, $this->unclaimedUserObj, $this->deviceToken);
    }

    public function testClaimUserAccessTokenNotFound()
    {
        $this->expectException(AccessTokenNotFoundException::class);
        $this->unclaimedUser->claimUser($this->unclaimedUserObj, $this->loggedUser, $this->deviceToken);
    }
}
