<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Scenarios;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\UserCreatedAtCriteria;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class UserCreatedAtCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
        ];
    }

    public static function dataProvider(): array
    {
        // all tests expect user to be created before 14 days (see prepareData())
        return [
            'Before_2days_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'days',
                'selectionValue' => 2,
                'expectedCount' => 1,
            ],
            'Before_16days_ShouldFindZero' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'days',
                'selectionValue' => 16,
                'expectedCount' => 0,
            ],
            'InTheLast_16days_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'days',
                'selectionValue' => 16,
                'expectedCount' => 1,
            ],
            'InTheLast_2days_ShouldFindZero' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'days',
                'selectionValue' => 2,
                'expectedCount' => 0,
            ],

            // test weeks
            'Before_1weeks_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'weeks',
                'selectionValue' => 1,
                'expectedCount' => 1,
            ],
            'Before_3weeks_ShouldFindZero' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'weeks',
                'selectionValue' => 3,
                'expectedCount' => 0,
            ],
            'InTheLast_3weeks_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'weeks',
                'selectionValue' => 3,
                'expectedCount' => 1,
            ],
            'InTheLast_1weeks_ShouldFindZero' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'weeks',
                'selectionValue' => 1,
                'expectedCount' => 0,
            ],

            // check edge of intervals - named operators in timeframe param are equal to these operators:
            // - "before" is <=
            // - "in the last" is >=
            'Before_14days_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'days',
                'selectionValue' => 14,
                'expectedCount' => 1,
            ],
            'InTheLast_14days_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'days',
                'selectionValue' => 14,
                'expectedCount' => 1,
            ],
            'Before_2weeks_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_BEFORE,
                'unitValue' => 'weeks',
                'selectionValue' => 2,
                'expectedCount' => 1,
            ],
            'InTheLast_2weeks_ShouldFindOne' => [
                'operatorValue' => UserCreatedAtCriteria::OPERATOR_IN_THE_LAST,
                'unitValue' => 'weeks',
                'selectionValue' => 2,
                'expectedCount' => 1,
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testCreatedAt(string $operatorValue, string $unitValue, int $selectionValue, int $expectedCount): void
    {
        [$userSelection, $userRow] = $this->prepareData();

        $criteria = $this->inject(UserCreatedAtCriteria::class);
        $values = (object)[
            'operator' => $operatorValue,
            'unit' => $unitValue,
            'selection' => $selectionValue,
        ];
        $criteria->addConditions($userSelection, [UserCreatedAtCriteria::TIMEFRAME_KEY => $values], $userRow);

        $this->assertEquals($expectedCount, $userSelection->count());
    }

    private function prepareData(): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $usersRepository->update($userRow, [
            'created_at' => (new DateTime())->modify('-14days'),
        ]);

        $userSelection = $usersRepository->getTable()
            ->where(['users.id' => $userRow->id]);

        return [$userSelection, $userRow];
    }
}
