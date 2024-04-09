<?php

declare(strict_types=1);

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repositories\GroupsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

/**
 * UserGroupsCriteria filters users by user group they belong to.
 *
 * Rules:
 * - User belongs to at least one of the selected groups (OR operator is used).
 * - User doesn't belong to any group and no group was selected in criteria.
 */
class UserGroupsCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'user_groups';

    public function __construct(
        private readonly GroupsRepository $groupsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        $groups = $this->groupsRepository->all()->fetchPairs('id', 'name');

        return [
            new StringLabeledArrayParam(
                key: self::KEY,
                label: $this->translator->translate('users.admin.scenarios.user_group.param'),
                options: $groups,
                operator: 'or',
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        if (empty($values->selection)) {
            // if no group is selected, criteria returns only users without group
            $selection->where(':user_groups.group_id IS NULL');
        } else {
            $selection->where(':user_groups.group_id IN (?)', array_keys($values->selection));
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.user_group.label');
    }
}
