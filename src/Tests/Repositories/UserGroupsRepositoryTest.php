<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Repositories;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;

class UserGroupsRepositoryTest extends DatabaseTestCase
{
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

        $this->userGroupsRepository = $this->getRepository(UserGroupsRepository::class);
        $this->groupsRepository = $this->getRepository(GroupsRepository::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testRemoveFromGroupSoftDeletes(): void
    {
        [$user, $group] = $this->createUserAndGroup();
        $this->userGroupsRepository->addToGroup($group, $user);

        $result = $this->userGroupsRepository->removeFromGroup($group, $user);

        $this->assertTrue($result);
        $this->assertFalse($this->userGroupsRepository->isMember($group, $user));

        $rawRow = $this->fetchRawRow($user, $group);
        $this->assertNotNull($rawRow);
        $this->assertNotNull($rawRow->deleted_at);
    }

    public function testReAddRestoresExistingRow(): void
    {
        [$user, $group] = $this->createUserAndGroup();
        $this->userGroupsRepository->addToGroup($group, $user);
        $firstCreatedAt = $this->fetchRawRow($user, $group)->created_at;

        $this->userGroupsRepository->removeFromGroup($group, $user);
        sleep(1); // ensure created_at timestamp changes
        $result = $this->userGroupsRepository->addToGroup($group, $user);

        $this->assertTrue($result);
        $this->assertTrue($this->userGroupsRepository->isMember($group, $user));

        $rowCount = $this->userGroupsRepository->getTable()
            ->where(['user_id' => $user->id, 'group_id' => $group->id])
            ->count('*');
        $this->assertSame(1, $rowCount);

        $row = $this->fetchRawRow($user, $group);
        $this->assertNull($row->deleted_at);
        $this->assertGreaterThan($firstCreatedAt, $row->created_at);
    }

    public function testGroupMembersExcludesSoftDeleted(): void
    {
        [$activeUser, $group] = $this->createUserAndGroup();
        $removedUser = $this->userManager->addNewUser('removed@test.sk');

        $this->userGroupsRepository->addToGroup($group, $activeUser);
        $this->userGroupsRepository->addToGroup($group, $removedUser);
        $this->userGroupsRepository->removeFromGroup($group, $removedUser);

        $members = $this->userGroupsRepository->groupMembers($group)->fetchAll();

        $this->assertCount(1, $members);
        $this->assertSame($activeUser->id, reset($members)->id);
    }

    public function testUserGroupsExcludesSoftDeleted(): void
    {
        $user = $this->userManager->addNewUser('user@test.sk');
        $activeGroup = $this->groupsRepository->add('active');
        $removedGroup = $this->groupsRepository->add('removed');

        $this->userGroupsRepository->addToGroup($activeGroup, $user);
        $this->userGroupsRepository->addToGroup($removedGroup, $user);
        $this->userGroupsRepository->removeFromGroup($removedGroup, $user);

        $groups = $this->userGroupsRepository->userGroups($user)->fetchAll();

        $this->assertCount(1, $groups);
        $this->assertSame($activeGroup->id, reset($groups)->id);
    }

    public function testRemoveFromGroupReturnsFalseWhenNotMember(): void
    {
        [$user, $group] = $this->createUserAndGroup();

        $this->assertFalse($this->userGroupsRepository->removeFromGroup($group, $user));
    }

    public function testRemoveFromGroupReturnsFalseWhenAlreadySoftDeleted(): void
    {
        [$user, $group] = $this->createUserAndGroup();
        $this->userGroupsRepository->addToGroup($group, $user);
        $this->userGroupsRepository->removeFromGroup($group, $user);

        $this->assertFalse($this->userGroupsRepository->removeFromGroup($group, $user));
    }

    public function testAddToGroupReturnsFalseWhenAlreadyActiveMember(): void
    {
        [$user, $group] = $this->createUserAndGroup();
        $this->userGroupsRepository->addToGroup($group, $user);

        $this->assertFalse($this->userGroupsRepository->addToGroup($group, $user));
    }

    public function testRemoveGroupsForUserHardDeletesAllRows(): void
    {
        $user = $this->userManager->addNewUser('user@test.sk');
        $otherUser = $this->userManager->addNewUser('other@test.sk');
        $activeGroup = $this->groupsRepository->add('active');
        $removedGroup = $this->groupsRepository->add('removed');

        $this->userGroupsRepository->addToGroup($activeGroup, $user);
        $this->userGroupsRepository->addToGroup($removedGroup, $user);
        $this->userGroupsRepository->removeFromGroup($removedGroup, $user);
        $this->userGroupsRepository->addToGroup($activeGroup, $otherUser);

        $deleted = $this->userGroupsRepository->removeGroupsForUser($user);

        $this->assertSame(2, $deleted);
        $this->assertSame(0, $this->userGroupsRepository->getTable()->where(['user_id' => $user->id])->count('*'));
        $this->assertSame(1, $this->userGroupsRepository->getTable()->where(['user_id' => $otherUser->id])->count('*'));
    }

    /**
     * @return array{ActiveRow, ActiveRow}
     */
    private function createUserAndGroup(): array
    {
        $user = $this->userManager->addNewUser('user@test.sk');
        $group = $this->groupsRepository->add('test-group');
        return [$user, $group];
    }

    private function fetchRawRow(ActiveRow $user, ActiveRow $group): ?ActiveRow
    {
        return $this->userGroupsRepository->getTable()
            ->where(['user_id' => $user->id, 'group_id' => $group->id])
            ->fetch();
    }
}
