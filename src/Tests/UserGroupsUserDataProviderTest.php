<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\DataProviders\UserGroupsUserDataProvider;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class UserGroupsUserDataProviderTest extends DatabaseTestCase
{
    private UserGroupsUserDataProvider $dataProvider;
    private UserGroupsRepository $userGroupsRepository;
    private GroupsRepository $groupsRepository;
    private UserManager $userManager;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            GroupsRepository::class,
            UserGroupsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataProvider = $this->inject(UserGroupsUserDataProvider::class);
        $this->userManager = $this->inject(UserManager::class);

        $this->userGroupsRepository = $this->getRepository(UserGroupsRepository::class);
        $this->groupsRepository = $this->getRepository(GroupsRepository::class);
    }

    public function testDeleteRemovesUserFromAllGroups(): void
    {
        $user = $this->userManager->addNewUser('user@test.sk');

        $group1 = $this->groupsRepository->add('group-1');
        $group2 = $this->groupsRepository->add('group-2');

        $this->userGroupsRepository->addToGroup($group1, $user);
        $this->userGroupsRepository->addToGroup($group2, $user);

        $this->dataProvider->delete($user->id);

        $this->assertSame(0, $this->userGroupsRepository->getTable()
            ->where(['user_id' => $user->id])
            ->count('*'));
    }
}
