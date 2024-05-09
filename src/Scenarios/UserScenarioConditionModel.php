<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\UsersModule\Repositories\UsersRepository;

class UserScenarioConditionModel implements ScenarioConditionModelInterface, ScenarioConditionModelRequirementsInterface
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
    ) {
    }

    public function getInputParams(): array
    {
        return ['user_id'];
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->user_id)) {
            throw new ConditionCheckException("User scenario conditional model requires 'user_id' job param.");
        }

        return $this->usersRepository->getTable()->where(['users.id' => $scenarioJobParameters->user_id]);
    }
}
