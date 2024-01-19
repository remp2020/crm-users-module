<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;

class EmailCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Email";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new StringArrayParam('email', "Email addresses", "Filters users with entered emails (no wildcards; full emails are required)", true, null),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $values = $params->stringArray('email')->escapedString();
        return "SELECT id FROM users WHERE email IN ($values)";
    }

    public function title(ParamsBag $params): string
    {
        return "with email {$params->stringArray('email')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }
}
