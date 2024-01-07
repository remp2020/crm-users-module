<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersUpdateHandler;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Events\UserUpdatedEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use Nette\Security\Passwords;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersUpdateHandlerTest extends DatabaseTestCase
{
    /** @var UsersRepository */
    private $usersRepository;

    /** @var UsersUpdateHandler */
    private $handler;

    /** @var Passwords */
    private $passwords;

    /** @var Emitter */
    private $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->passwords = $this->inject(Passwords::class);
        $this->emitter = $this->inject(Emitter::class);

        $this->handler = $this->inject(UsersUpdateHandler::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    public function testUpdateUserNotExist()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => 1]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(404, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('user_not_found', $payload['code']);
    }

    public function testUpdateUserChangeEmail()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserUpdatedEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerChangePassword */
        $listenerChangePassword = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserChangePasswordEvent::class, $listenerChangePassword);

        $newEmail = 'new_test@user.site';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'email' => $newEmail]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertEquals($newEmail, $user->email);
    }

    public function testUpdateUserInvalidEmail()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        $newEmail = 'new_test_user.site';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'email' => $newEmail]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(400, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('invalid_email', $payload['code']);
    }

    public function testUpdateUserInvalidEmailNoValidation()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserUpdatedEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerChangePassword */
        $listenerChangePassword = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserChangePasswordEvent::class, $listenerChangePassword);

        $newEmail = 'new_test_user.site';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'email' => $newEmail, 'disable_email_validation' => '1']);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertEquals($newEmail, $user->email);
    }

    public function testUpdateUserChangePublicName()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        $newEmail = 'new_test@user.site';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'email' => $newEmail]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertEquals($newEmail, $user->public_name);
    }

    public function testUpdateUserPublicNameNoChange()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');
        $publicName = 'Test Public Name';
        $this->usersRepository->update($user, ['public_name' => $publicName]);

        $newEmail = 'new_test@user.site';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'email' => $newEmail]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertEquals($publicName, $user->public_name);
    }

    public function testUpdateUserChangeExtId()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        $extId = 42;
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'ext_id' => $extId]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertEquals($extId, $user->ext_id);
    }

    public function testUpdateUserChangePassword()
    {
        $email = '0test2@user.site';
        $user = $this->usersRepository->add($email, '123456');

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserUpdatedEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerChangePassword */
        $listenerChangePassword = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserChangePasswordEvent::class, $listenerChangePassword);

        $newPassword = 'abcdef';
        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'password' => $newPassword]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertTrue($this->passwords->verify($newPassword, $user->password));
    }

    public function testUpdateUserSamePassword()
    {
        $email = '0test2@user.site';
        $password = '123456';
        $user = $this->usersRepository->add($email, $password);

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserUpdatedEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerChangePassword */
        $listenerChangePassword = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserChangePasswordEvent::class, $listenerChangePassword);

        $this->handler->setAuthorization(new NoAuthorization());
        /** @var JsonApiResponse $response */
        $response = $this->handler->handle(['user_id' => $user->id, 'password' => $password]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $user = $this->usersRepository->find($user->id);
        $this->assertTrue($this->passwords->verify($password, $user->password));
    }
}
