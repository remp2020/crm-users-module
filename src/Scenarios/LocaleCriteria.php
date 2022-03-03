<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Database\Table\IRow;
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

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
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