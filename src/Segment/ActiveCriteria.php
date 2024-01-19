<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\BooleanParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class ActiveCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Active";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new BooleanParam('active', "Is active", "Filters users who are / aren't active", true, true),
        ];
    }

    public function join(ParamsBag $params): string
    {
        return "SELECT id FROM users WHERE active = " . $params->boolean('active')->number();
    }

    public function title(ParamsBag $params): string
    {
        if ($params->boolean('active')->isTrue()) {
            return ' active';
        } else {
            return ' inactive';
        }
    }

    public function fields(): array
    {
        return [];
    }
}
