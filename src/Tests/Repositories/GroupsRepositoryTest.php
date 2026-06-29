<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Repositories;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Repositories\GroupsRepository;

class GroupsRepositoryTest extends DatabaseTestCase
{
    private GroupsRepository $groupsRepository;

    protected function requiredRepositories(): array
    {
        return [
            GroupsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupsRepository = $this->getRepository(GroupsRepository::class);
    }

    public function testSoftDeleteHidesGroup(): void
    {
        $group = $this->groupsRepository->add('test-group');

        $this->groupsRepository->softDelete($group);

        $this->assertSame(0, $this->groupsRepository->all()->count('*'));
        $this->assertFalse($this->groupsRepository->exists('test-group'));
        $this->assertNull($this->groupsRepository->findByName('test-group'));

        $rawRow = $this->groupsRepository->getTable()->where('name', 'test-group')->fetch();
        $this->assertNotNull($rawRow);
        $this->assertNotNull($rawRow->deleted_at);
    }

    public function testFindReturnsActiveGroup(): void
    {
        $group = $this->groupsRepository->add('test-group');

        $found = $this->groupsRepository->find($group->id);

        $this->assertNotNull($found);
        $this->assertSame($group->id, $found->id);
    }

    public function testFindReturnsNullForSoftDeletedGroup(): void
    {
        $group = $this->groupsRepository->add('test-group');
        $this->groupsRepository->softDelete($group);

        $this->assertNull($this->groupsRepository->find($group->id));
    }

    public function testGroupCanBeRecreatedAfterSoftDelete(): void
    {
        $group = $this->groupsRepository->add('test-group');
        $this->groupsRepository->softDelete($group);

        $newGroup = $this->groupsRepository->add('test-group');

        $this->assertNotSame($group->id, $newGroup->id);
        $this->assertTrue($this->groupsRepository->exists('test-group'));
        $this->assertSame($newGroup->id, $this->groupsRepository->findByName('test-group')->id);
    }
}
