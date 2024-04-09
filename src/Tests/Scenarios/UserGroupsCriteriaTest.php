<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Scenarios;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\UserGroupsCriteria;
use PHPUnit\Framework\Attributes\DataProvider;

class UserGroupsCriteriaTest extends DatabaseTestCase
{
    public function requiredRepositories(): array
    {
        return [
            GroupsRepository::class,
            UsersRepository::class,
            UserGroupsRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [];
    }

    public static function dataProvider(): array
    {
        return [
            // user in no group
            'userInNoGroup_selectedNoGroup' => [
                'userIsInGroupName' => [],
                'groupNamesForCriteriaSelection' => [],
                'expectedResult' => true, // no group selected; filters all users without group
            ],
            'userInNoGroup_selectedOneGroup' => [
                'userIsInGroupName' => [],
                'groupNamesForCriteriaSelection' => ['student'],
                'expectedResult' => false,
            ],
            'userInNoGroup_selectedTwoGroup' => [
                'userIsInGroupName' => [],
                'groupNamesForCriteriaSelection' => ['student', 'crowdfunding'],
                'expectedResult' => false,
            ],

            // user in single group
            'userInSingleGroup_selectedNoGroup' => [
                'userIsInGroupName' => ['student'],
                'groupNamesForCriteriaSelection' => [],
                'expectedResult' => false, // no group selected; filters all users without group
            ],
            'userInSingleGroup_selectedSameGroup' => [
                'userIsInGroupName' => ['student'],
                'groupNamesForCriteriaSelection' => ['student'],
                'expectedResult' => true,
            ],
            'userInSingleGroup_selectedTwoGroupsOneIsSame' => [
                'userIsInGroupName' => ['student'],
                'groupNamesForCriteriaSelection' => ['student', 'crowdfunding'],
                'expectedResult' => true,
            ],
            'userInSingleGroup_selectedDifferentGroup' => [
                'userIsInGroupName' => ['student'],
                'groupNamesForCriteriaSelection' => ['teacher'],
                'expectedResult' => false,
            ],

            // user in two groups
            'userInTwoGroups_selectedNoGroup' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => [],
                'expectedResult' => false, // no group selected; filters all users without group
            ],
            'userInTwoGroups_selectedOneOfThem' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => ['student'],
                'expectedResult' => true,
            ],
            'userInTwoGroups_selectedDifferentGroup' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => ['teacher'],
                'expectedResult' => false,
            ],
            'userInTwoGroups_selectedTwoOfThem' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => ['student', 'crowdfunding'],
                'expectedResult' => true,
            ],
            'userInTwoGroups_selectedTwoGroupsAndOneIsSame' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => ['teacher', 'crowdfunding'],
                'expectedResult' => true,
            ],
            'userInTwoGroups_selectedTwoDifferentGroups' => [
                'userIsInGroupName' => ['student', 'crowdfunding'],
                'groupNamesForCriteriaSelection' => ['teacher', 'elections'],
                'expectedResult' => false,
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testUserGroupsCriteria(
        array $userIsInGroupNames,
        array $groupNamesForCriteriaSelection,
        bool $expectedResult,
    ) : void {
        [$userRow, $userSelection, $groupsForCriteriaSelection] = $this->prepareData(
            $userIsInGroupNames,
            $groupNamesForCriteriaSelection,
        );

        /** @var UserGroupsCriteria $criteria */
        $criteria = $this->inject(UserGroupsCriteria::class);
        $values = (object)['selection' => $groupsForCriteriaSelection];
        $criteria->addConditions($userSelection, [UserGroupsCriteria::KEY => $values], $userRow);

        if ($expectedResult) {
            $this->assertNotNull($userSelection->fetch());
        } else {
            $this->assertNull($userSelection->fetch());
        }
    }

    private function prepareData(array $userIsInGroupNames, array $groupNamesForCriteriaSelection): array
    {
        // prepare user and user selection for scenario criteria
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        /** @var GroupsRepository $groupsRepository */
        $groupsRepository = $this->getRepository(GroupsRepository::class);
        /** @var UserGroupsRepository $userGroupsRepository */
        $userGroupsRepository = $this->getRepository(UserGroupsRepository::class);
        // add user groups to user
        foreach ($userIsInGroupNames as $userIsInGroupName) {
            $userGroupRow = $groupsRepository->add($userIsInGroupName);
            $userGroupsRepository->addToGroup($userGroupRow, $userRow);
        }

        // prepare group selection for criterion (and seed missing groups)
        $groupsForCriteriaSelection = [];
        foreach ($groupNamesForCriteriaSelection as $groupName) {
            $groupRow = $groupsRepository->findBy('name', $groupName);
            if ($groupRow === null) {
                $groupRow = $groupsRepository->add($groupName);
            }
            $groupsForCriteriaSelection[$groupRow->id] = $groupRow->name;
        }

        // prepare user selection for criterion
        $userSelection = $usersRepository->getTable()->where('email = ?', $userRow->email);

        return [$userRow, $userSelection, $groupsForCriteriaSelection];
    }
}
