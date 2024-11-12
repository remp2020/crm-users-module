<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\DateTimeParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class SourceAccessCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return 'Source of web access';
    }

    public function category(): string
    {
        return 'Users';
    }

    public function params(): array
    {
        return [
            new DateTimeParam('web_mobile', "Last web visit on mobile", "Filter users who visited web on mobile within selected period", false),
            new DateTimeParam('web_desktop', "Last web visit on desktop", "Filter users who visited web on desktop within selected period", false),
            new DateTimeParam('web_tablet', "Last web visit on table", "Filter users who visited web on tablet within selected period", false),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $havingCount = 0;
        $where = [];
        if ($params->has('web_mobile')) {
            $dateWhere = $params->datetime('web_mobile')->escapedConditions('last_accessed_at');
            $date = implode(" AND ", $dateWhere);
            $where[] = "($date AND source='web_mobile')";
            $havingCount++;
        }
        if ($params->has('web_desktop')) {
            $dateWhere = $params->datetime('web_desktop')->escapedConditions('last_accessed_at');
            $date = implode(" AND ", $dateWhere);
            $where[] = "($date AND source='web')";
            $havingCount++;
        }
        if ($params->has('web_tablet')) {
            $dateWhere = $params->datetime('web_tablet')->escapedConditions('last_accessed_at');
            $date = implode(" AND ", $dateWhere);
            $where[] = "($date AND source='web_tablet')";
            $havingCount++;
        }

        $where = ' WHERE ' . implode(' OR ', $where);
        return "SELECT user_id AS id FROM user_source_accesses $where GROUP BY user_id HAVING COUNT(*) >= $havingCount";
    }

    public function title(ParamsBag $params): string
    {
        $titles = [];
        if ($params->has('web_mobile')) {
            $titles[] = 'mobile having' . $params->datetime('web_mobile')->title('last_accessed_at');
        }
        if ($params->has('web_desktop')) {
            $titles[] = 'desktop having' . $params->datetime('web_desktop')->title('last_accessed_at');
        }
        if ($params->has('web_tablet')) {
            $titles[] = 'tablet having' . $params->datetime('web_tablet')->title('last_accessed_at');
        }

        return ' source access via ' . implode(' and ', $titles);
    }

    public function fields(): array
    {
        return [];
    }
}
