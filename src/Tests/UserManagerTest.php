<?php

namespace Crm\UsersModule\Tests;

use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Models\Auth\InvalidEmailException;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UserAlreadyExistsException;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;

class UserManagerTest extends BaseTestCase
{
    /** @var UsersRepository */
    private $usersRepository;

    /** @var UserManager */
    private $userManager;

    /** @var Emitter */
    private $emitter;

    public function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->emitter = $this->inject(Emitter::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    public function testAddingNewUser()
    {
        $newUserEventListener = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $newUserEventListener);
        $userRegisteredEventListener = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $userRegisteredEventListener);

        $user = $this->userManager->addNewUser("admin@example.com");

        $this->assertEquals("admin@example.com", $user->email);
        $user = $this->usersRepository->getByEmail("admin@example.com");
        $this->assertEquals("admin@example.com", $user->email);
    }

    public function testAddingExistingUser()
    {
        $this->expectException(UserAlreadyExistsException::class);
        $this->userManager->addNewUser("admin@example.com");
        $this->userManager->addNewUser("admin@example.com");
    }

    public function testAddingInvalidEmail()
    {
        $this->expectException(InvalidEmailException::class);
        $this->userManager->addNewUser('admin');
    }

    public function testAllowingInvalidEmail()
    {
        $user = $this->userManager->addNewUser('admin', false, 'unknown', null, false);
        $this->assertEquals('admin', $user->email);
    }
}
