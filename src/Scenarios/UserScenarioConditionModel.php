<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class UserScenarioConditionModel implements ScenarioConditionModelInterface
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
    ) {
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->user_id)) {
            throw new Exception("User scenario conditional model requires 'user_id' job param.");
        }

        return $this->usersRepository->getTable()->where(['users.id' => $scenarioJobParameters->user_id]);
    }
}
