<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\LocaleCriteria;
use PHPUnit\Framework\Attributes\DataProvider;

class LocaleCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [UsersRepository::class];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    public static function dataProvider(): array
    {
        return [
            [true, 'sk_SK', 'sk_SK'],
            [false, 'hu_HU', 'sk_SK'],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testIsLocale(bool $isSelected, string $selectedLocale, string $userLocale): void
    {
        [$userSelection, $userRow] = $this->prepareData($userLocale);

        /** @var LocaleCriteria $criteria */
        $criteria = $this->inject(LocaleCriteria::class);
        $values = (object)['selection' => [$selectedLocale]];
        $criteria->addConditions($userSelection, [LocaleCriteria::KEY => $values], $userRow);

        $this->assertEquals($isSelected, (bool) $userSelection->fetch());
    }

    private function prepareData(string $locale): array
    {
        /** @var UserBuilder $userBuilder */
        $userBuilder = $this->inject(UserBuilder::class);
        $email = 'test@example.com';
        $userRow = $userBuilder->createNew()
            ->setEmail($email)
            ->setPublicName($email)
            ->setPassword('secret', false)
            ->setLocale($locale)
            ->save();

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->inject(UsersRepository::class);
        $userSelection = $usersRepository->getTable()->where(['users.id' => $userRow->id]);
        return [$userSelection, $userRow];
    }
}
