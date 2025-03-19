<?php
declare(strict_types=1);

namespace Crm\UsersModule\Tests;

use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\AddressScenarioConditionModel;

class AddressScenarioConditionModelTest extends BaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            AddressesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ...parent::requiredSeeders(),
            AddressTypesSeeder::class
        ];
    }

    public function testItemQuery(): void
    {
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        $address = $addressesRepository->add(
            $user,
            'invoice',
            firstName: 'John',
            lastName: 'Doe',
            street: 'Main Street',
            number: '123',
            city: 'New York',
            zip: '12345',
            countryId: null,
            phoneNumber: '+1234567890',
        );

        $addressScenarioConditionModel = new AddressScenarioConditionModel($addressesRepository);
        $selection = $addressScenarioConditionModel->getItemQuery((object) [
            'address_id' => $address->id,
        ]);

        $this->assertCount(1, $selection->fetchAll());
    }

    public function testItemQueryWithWrongId(): void
    {
        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);

        $addressScenarioConditionModel = new AddressScenarioConditionModel($addressesRepository);
        $selection = $addressScenarioConditionModel->getItemQuery((object) [
            'address_id' => 1,
        ]);

        $this->assertEmpty($selection->fetchAll());
    }

    public function testItemQueryWithoutMandatoryJobParameter(): void
    {
        $this->expectException(ConditionCheckException::class);
        $this->expectExceptionMessage("Address scenario conditional model requires 'address_id' job param.");

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);

        $addressScenarioConditionModel = new AddressScenarioConditionModel($addressesRepository);
        $addressScenarioConditionModel->getItemQuery((object) []);
    }
}
