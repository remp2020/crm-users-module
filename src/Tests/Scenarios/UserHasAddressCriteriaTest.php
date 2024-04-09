<?php

namespace Crm\UsersModule\Tests\Scenarios;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\UserHasAddressCriteria;
use PHPUnit\Framework\Attributes\DataProvider;

class UserHasAddressCriteriaTest extends DatabaseTestCase
{
    public function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            CountriesRepository::class,
            AddressTypesRepository::class,
            UsersRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
        ];
    }

    public static function dataProviderForTestUserHasAddressCriteria(): array
    {
        return [
            [
                'hasAddresses' => ['invoice'],
                'shouldHaveOneOfAddressTypes' => ['invoice'],
                'expectedResult' => true
            ],
            [
                'hasAddresses' => [],
                'shouldHaveOneOfAddressTypes' => ['invoice'],
                'expectedResult' => false
            ],
            [
                'hasAddresses' => ['print'],
                'shouldHaveOneOfAddressTypes' => ['print', 'invoice'],
                'expectedResult' => true
            ],
            [
                'hasAddresses' => ['print_friday'],
                'shouldHaveOneOfAddressTypes' => ['print', 'invoice'],
                'expectedResult' => false
            ],
        ];
    }

    #[DataProvider('dataProviderForTestUserHasAddressCriteria')]
    public function testUserHasAddressCriteriaTest(array $hasAddresses, array $shouldHaveOneOfAddressTypes, bool $expectedResult) : void
    {
        [$userSelection, $userRow] = $this->prepareData($hasAddresses);

        $criteria = $this->inject(UserHasAddressCriteria::class);
        $values = (object)['selection' => $shouldHaveOneOfAddressTypes];
        $criteria->addConditions($userSelection, [UserHasAddressCriteria::KEY => $values], $userRow);

        if ($expectedResult) {
            $this->assertNotNull($userSelection->fetch());
        } else {
            $this->assertNull($userSelection->fetch());
        }
    }

    private function prepareData(array $withAddresses): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        if (!empty($withAddresses)) {
            foreach ($withAddresses as $addressTypeCode) {

                /** @var CountriesRepository $countriesRepository */
                $countriesRepository = $this->getRepository(CountriesRepository::class);
                $country = $countriesRepository->findByIsoCode('SK');

                /** @var AddressTypesRepository $addressTypesRepository */
                $addressTypesRepository = $this->getRepository(AddressTypesRepository::class);
                $addressTypesRepository->add($addressTypeCode, $addressTypeCode);

                /** @var AddressesRepository $addressesRepository */
                $addressesRepository = $this->getRepository(AddressesRepository::class);
                $addressesRepository->add(
                    $userRow,
                    $addressTypeCode,
                    'Test',
                    'Test',
                    'Test',
                    'Test',
                    'Test',
                    'Test',
                    $country->id,
                    'Test'
                );
            }
        }

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $userSelection = $usersRepository->getTable()->where('email = ?', 'test@test.sk');

        return [$userSelection, $userRow];
    }
}
