<?php
declare(strict_types=1);

namespace Crm\UsersModule\Tests;

use Crm\ProductsModule\Repositories\OrdersRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\UserScenarioConditionModel;
use Exception;

class UserScenarioConditionModelTest extends BaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            OrdersRepository::class,
        ];
    }

    public function testItemQuery(): void
    {
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        $userScenarioConditionModel = new UserScenarioConditionModel($usersRepository);
        $selection = $userScenarioConditionModel->getItemQuery((object) [
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $selection->fetchAll());
    }

    public function testItemQueryWithWrongId(): void
    {
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        $userScenarioConditionModel = new UserScenarioConditionModel($usersRepository);
        $selection = $userScenarioConditionModel->getItemQuery((object) [
            'user_id' => 1,
        ]);

        $this->assertEmpty($selection->fetchAll());
    }

    public function testItemQueryWithoutMandatoryJobParameter(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("User scenario conditional model requires 'user_id' job param.");

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        $userScenarioConditionModel = new UserScenarioConditionModel($usersRepository);
        $userScenarioConditionModel->getItemQuery((object) []);
    }
}
