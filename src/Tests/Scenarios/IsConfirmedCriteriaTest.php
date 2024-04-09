<?php

namespace Crm\UsersModule\Tests\Scenarios;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\IsConfirmedCriteria;
use PHPUnit\Framework\Attributes\DataProvider;

class IsConfirmedCriteriaTest extends DatabaseTestCase
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
        return [
            'Confirmed_CheckConfirmed_ShouldReturnTrue' => [
                'isConfirmed' => true,
                'selectedValue' => true,
                'expectedValue' => true,
            ],
            'Confirmed_CheckNotConfirmed_ShouldReturnFalse' => [
                'isConfirmed' => true,
                'selectedValue' => false,
                'expectedValue' => false,
            ],
            'NotConfirmed_CheckConfirmed_ShouldReturnFalse' => [
                'isConfirmed' => false,
                'selectedValue' => true,
                'expectedValue' => false,
            ],
            'NotConfirmed_CheckNotConfirmed_ShouldReturnTrue' => [
                'isConfirmed' => false,
                'selectedValue' => false,
                'expectedValue' => true,
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testIsConfirmed(bool $isConfirmed, bool $selectedValue, bool $expectedValue): void
    {
        [$userSelection, $userRow] = $this->prepareData($isConfirmed);

        $criteria = $this->inject(IsConfirmedCriteria::class);
        $values = (object)['selection' => $selectedValue];
        $criteria->addConditions($userSelection, ['is_confirmed' => $values], $userRow);

        $this->assertEquals($expectedValue, (bool) $userSelection->fetch());
    }

    private function prepareData(bool $isConfirmed): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->inject(UsersRepository::class);
        $usersRepository->update($userRow, [
            'confirmed_at' => $isConfirmed ? new \DateTime() : null,
        ]);

        $userSelection = $usersRepository->getTable()
            ->where(['users.id' => $userRow->id]);

        return [$userSelection, $userRow];
    }
}
