<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Models\User\ReachChecker;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;

class ReachCheckerTest extends DatabaseTestCase
{
    private ActiveRow $user;

    private ReachChecker $reachChecker;

    private UsersRepository $usersRepository;

    private UserMetaRepository $userMetaRepository;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var UserBuilder $userBuilder */
        $userBuilder = $this->inject(UserBuilder::class);
        $this->reachChecker = $this->inject(ReachChecker::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userMetaRepository = $this->getRepository(UserMetaRepository::class);
        $this->user = $userBuilder->createNew()
            ->setEmail('admin@example.com')
            ->setPublicName('Example Admin')
            ->setPassword('secret', false)
            ->save();
    }

    public function testReachableUser()
    {
        $this->assertTrue(
            $this->reachChecker->isReachable($this->user)
        );
    }

    public function testNotReachableDeleted()
    {
        $this->usersRepository->update($this->user, [
            'deleted_at' => new \DateTime(),
        ]);
        $this->assertFalse(
            $this->reachChecker->isReachable($this->user)
        );
    }

    public function testNotReachableInactive()
    {
        $this->usersRepository->update($this->user, [
            'active' => false,
        ]);
        $this->assertFalse(
            $this->reachChecker->isReachable($this->user)
        );
    }

    public function testNotReachableUnclaimed()
    {
        /** @var UnclaimedUser $unclaimedUser */
        $unclaimedUser = $this->inject(UnclaimedUser::class);
        $user = $unclaimedUser->createUnclaimedUser('unclaimed@example.com');
        $this->assertFalse(
            $this->reachChecker->isReachable($user)
        );
    }

    public function testNotReachableMetaFlag()
    {
        $this->userMetaRepository->setMeta($this->user, [ReachChecker::USER_META_UNREACHABLE => true]);
        $this->assertFalse(
            $this->reachChecker->isReachable($this->user)
        );
    }
}
