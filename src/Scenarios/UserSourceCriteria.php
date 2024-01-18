<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

class UserSourceCriteria implements ScenariosCriteriaInterface
{
    private $usersRepository;

    private $translator;

    public function __construct(UsersRepository $usersRepository, Translator $translator)
    {
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
    }

    public function params(): array
    {
        $sources = $this->usersRepository->getUserSources();

        // Allow entering arbitrary values (free-solo mode)
        return [
            new StringLabeledArrayParam('source', 'Source', $sources, 'or', true),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['source'];
        $selection->where('users.source IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('users.admin.scenarios.source.label');
    }
}
