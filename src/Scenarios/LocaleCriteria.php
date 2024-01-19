<?php

namespace Crm\UsersModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class LocaleCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'locale';

    private Translator $translator;

    private UsersRepository $usersRepository;

    public function __construct(
        UsersRepository $usersRepository,
        Translator $translator
    ) {
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
    }

    public function params(): array
    {
        $locales = $this->usersRepository->getTable()
            ->select("users.locale")
            ->group('users.locale')
            ->fetchAssoc('locale=locale');

        return [
            new StringLabeledArrayParam(
                self::KEY,
                $this->translator->translate('users.admin.scenarios.locale.label'),
                $locales,
                'or',
                true
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('users.locale IN (?)', $values->selection);
        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.locale.label');
    }
}
