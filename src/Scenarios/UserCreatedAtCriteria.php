<?php

declare(strict_types=1);

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\TimeframeParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\ScenariosModule\Scenarios\TimeframeScenarioTrait;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

class UserCreatedAtCriteria implements ScenariosCriteriaInterface
{
    use TimeframeScenarioTrait;

    public const KEY = 'user_created_at';
    public const TIMEFRAME_KEY = self::KEY . '_timeframe';
    public const UNITS = ['minutes', 'hours', 'days', 'weeks', 'months', 'years'];
    public const OPERATOR_BEFORE = 'before';
    public const OPERATOR_IN_THE_LAST = 'in the last';
    public const OPERATORS = [self::OPERATOR_IN_THE_LAST, self::OPERATOR_BEFORE];

    public function __construct(private readonly Translator $translator)
    {
    }

    public function params(): array
    {
        return [
            new TimeframeParam(
                self::TIMEFRAME_KEY,
                '',
                $this->translator->translate('users.admin.scenarios.created_at.timeframe_param.amount_label'),
                $this->translator->translate('users.admin.scenarios.created_at.timeframe_param.units_label'),
                array_values(self::OPERATORS),
                self::UNITS
            )
        ];
    }

    /**
     * @throws \Exception
     */
    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $timeframe = $this->getTimeframe($paramValues, self::UNITS, self::OPERATORS, self::TIMEFRAME_KEY);
        if (!$timeframe) {
            return false;
        }

        $operator = $timeframe['operator'] === self::OPERATOR_BEFORE ? '<=' : '>=';
        $selection->where("users.created_at {$operator} ?", $timeframe['limit']);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.created_at.label');
    }
}
