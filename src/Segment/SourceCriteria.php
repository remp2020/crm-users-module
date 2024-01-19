<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;
use Crm\UsersModule\Repositories\UsersRepository;

class SourceCriteria implements CriteriaInterface
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    private function availableSources(): array
    {
        return array_keys($this->usersRepository->getUserSources());
    }

    public function label(): string
    {
        return "Source";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new StringArrayParam('source', "Sources", "Filters users registered with selected sources", true, null, null, $this->availableSources()),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $values = $params->stringArray('source')->escapedString();
        return "SELECT id FROM users WHERE source IN ($values)";
    }

    public function title(ParamsBag $params): string
    {
        return "source {$params->stringArray('source')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }
}
