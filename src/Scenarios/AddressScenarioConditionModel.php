<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\UsersModule\Repositories\AddressesRepository;
use Exception;

class AddressScenarioConditionModel implements ScenarioConditionModelInterface
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->address_id)) {
            throw new Exception("Address scenario conditional model requires 'address_id' job param.");
        }

        return $this->addressesRepository->getTable()->where(['addresses.id' => $scenarioJobParameters->address_id]);
    }
}
