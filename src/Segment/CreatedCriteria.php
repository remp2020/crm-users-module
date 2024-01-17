<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\DateTimeParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class CreatedCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Created";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new DateTimeParam('created', "Created", "Filters users created within selected period", true),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];
        $where += $params->datetime('created')->escapedConditions('users.created_at');
        return "SELECT id FROM users WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $paramBag): string
    {
        return "created{$paramBag->datetime('created')->title('users.created_at')}";
    }

    public function fields(): array
    {
        return [];
    }
}
