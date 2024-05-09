<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\UsersModule\Repositories\AddressesRepository;

class AddressScenarioConditionModel implements ScenarioConditionModelInterface, ScenarioConditionModelRequirementsInterface
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function getInputParams(): array
    {
        return ['address_id'];
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->address_id)) {
            throw new ConditionCheckException("Address scenario conditional model requires 'address_id' job param.");
        }

        return $this->addressesRepository->getTable()->where(['addresses.id' => $scenarioJobParameters->address_id]);
    }
}
