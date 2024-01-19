<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

class IsConfirmedCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is_user_confirmed';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam('is_confirmed', $this->translator->translate('users.admin.scenarios.is_confirmed.param')),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['is_confirmed'];

        if ($values->selection) {
            $selection->where('confirmed_at IS NOT NULL');
        } else {
            $selection->where('confirmed_at IS NULL');
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.is_confirmed.label');
    }
}
