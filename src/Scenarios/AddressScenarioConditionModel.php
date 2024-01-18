<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Selection;
use Crm\UsersModule\Repositories\AddressesRepository;

class AddressScenarioConditionModel implements ScenarioConditionModelInterface
{
    private $addressesRepository;

    public function __construct(AddressesRepository $addressesRepository)
    {
        $this->addressesRepository = $addressesRepository;
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->address_id)) {
            throw new \Exception("Address scenario conditional model requires 'address_id' job param.");
        }

        return $this->addressesRepository->getTable()->where(['addresses.id' => $scenarioJobParameters->address_id]);
    }
}
