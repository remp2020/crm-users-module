<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Models;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\AdminFilterFormData;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class AdminFilterFormDataTest extends DatabaseTestCase
{
    private AdminFilterFormData $adminFilterFormData;
    private GroupsRepository $groupsRepository;
    private UserGroupsRepository $userGroupsRepository;
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

        $this->adminFilterFormData = $this->inject(AdminFilterFormData::class);
        $this->groupsRepository = $this->getRepository(GroupsRepository::class);
        $this->userGroupsRepository = $this->getRepository(UserGroupsRepository::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testFilterByGroupReturnsMembers(): void
    {
        $user = $this->userManager->addNewUser('user@test.sk');
        $group = $this->groupsRepository->add('test-group');
        $this->userGroupsRepository->addToGroup($group, $user);

        $this->adminFilterFormData->parse(['group' => $group->id]);

        $filtered = $this->adminFilterFormData->getFilteredUsers();

        $this->assertSame(1, $filtered->count('*'));
        $this->assertSame($user->id, $filtered->fetch()->id);
    }

    public function testFilterByGroupExcludesRemovedMembers(): void
    {
        $user = $this->userManager->addNewUser('user@test.sk');
        $group = $this->groupsRepository->add('test-group');
        $this->userGroupsRepository->addToGroup($group, $user);
        $this->userGroupsRepository->removeFromGroup($group, $user);

        $this->adminFilterFormData->parse(['group' => $group->id]);

        $this->assertSame(0, $this->adminFilterFormData->getFilteredUsers()->count('*'));
    }
}
