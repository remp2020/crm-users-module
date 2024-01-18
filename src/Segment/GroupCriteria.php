<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\NumberArrayParam;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\UsersModule\Repositories\GroupsRepository;

class GroupCriteria implements CriteriaInterface
{
    private $groupsRepository;

    public function __construct(GroupsRepository $groupsRepository)
    {
        $this->groupsRepository = $groupsRepository;
    }

    public function label(): string
    {
        return "Group";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new NumberArrayParam('group_id', "Group", "Filters users who are / aren't in any of specified groups", true, null, null, $this->groupsRepository->all()->fetchPairs('id', 'name')),
        ];
    }

    public function join(ParamsBag $params): string
    {
        return "SELECT DISTINCT(user_id) AS id FROM user_groups WHERE group_id IN ({$params->numberArray('group_id')->escapedString()})";
    }

    public function title(ParamsBag $params): string
    {
        return ' in group ' . $params->numberArray('group_id')->escapedString();
    }

    public function fields(): array
    {
        return [];
    }
}
