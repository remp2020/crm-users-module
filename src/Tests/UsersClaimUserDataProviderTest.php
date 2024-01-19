<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\DataProviders\UsersClaimUserDataProvider;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Database\Table\ActiveRow;

class UsersClaimUserDataProviderTest extends DatabaseTestCase
{
    private $dataProvider;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UserMetaRepository */
    private $userMetaRepository;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $unclaimedUserObj;

    private $loggedUser;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataProvider = $this->inject(UsersClaimUserDataProvider::class);

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userMetaRepository = $this->getRepository(UserMetaRepository::class);

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN);
    }

    public function testWrongArguments(): void
    {
        $this->expectException(DataProviderException::class);
        $this->dataProvider->provide([]);
    }

    public function testClaimUserMeta(): void
    {
        $this->userMetaRepository->add($this->loggedUser, 'keep', 9);
        $this->userMetaRepository->add($this->unclaimedUserObj, 'test', 1);
        $this->userMetaRepository->add($this->unclaimedUserObj, 'keep', 1);

        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEquals([UnclaimedUser::META_KEY => 1], $this->userMetaRepository->userMeta($this->unclaimedUserObj));

        $loggedUserMetas = $this->userMetaRepository->userMeta($this->loggedUser);
        $this->assertEquals(1, $loggedUserMetas['test']);
        $this->assertEquals(9, $loggedUserMetas['keep']);
        $this->assertArrayNotHasKey(UnclaimedUser::META_KEY, $loggedUserMetas);
    }

    public function testClaimUserNote(): void
    {
        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        // empty note
        $this->assertEquals(null, $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN)->note);

        $this->unclaimedUserObj->update(['note' => 'test']);
        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEquals('test', $this->usersRepository->getByEmail($this->loggedUser->email)->note);

        $this->usersRepository->update($this->loggedUser, ['note' => 'note']);
        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEquals("note\ntest", $this->usersRepository->getByEmail($this->loggedUser->email)->note);
    }

    private function loadUser(string $email, string $password, $role = UsersRepository::ROLE_USER, $active = true): ActiveRow
    {
        $user = $this->usersRepository->getByEmail($email);
        if (!$user) {
            $user = $this->usersRepository->add($email, $password, $role, $active);
        }

        return $user;
    }
}
