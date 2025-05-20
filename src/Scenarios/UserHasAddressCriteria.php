<?php

namespace Crm\UsersModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class UserHasAddressCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'user_has_address_type';

    private $addressTypesRepository;

    private $translator;

    public function __construct(
        AddressTypesRepository $addressTypesRepository,
        Translator $translator,
    ) {
        $this->addressTypesRepository = $addressTypesRepository;
        $this->translator = $translator;
    }

    public function params(): array
    {
        $addressTypes = $this->addressTypesRepository->getPairs();

        return [
            new StringLabeledArrayParam(
                self::KEY,
                $this->translator->translate('users.admin.scenarios.user_has_address_criteria.param'),
                $addressTypes,
                'or',
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $addressTypes = $paramValues[self::KEY]->selection;

        $selection->where(
            ':addresses.id IS NOT NULL AND :addresses.type IN (?)',
            $addressTypes,
        );
        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.user_has_address_criteria.label');
    }
}
