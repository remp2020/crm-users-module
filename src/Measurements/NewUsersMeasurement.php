<?php

namespace Crm\UsersModule\Measurements;

use Crm\ApplicationModule\Models\Measurements\Aggregation\DateData;
use Crm\ApplicationModule\Models\Measurements\BaseMeasurement;
use Crm\ApplicationModule\Models\Measurements\Criteria;
use Crm\ApplicationModule\Models\Measurements\Point;
use Crm\ApplicationModule\Models\Measurements\Series;

class NewUsersMeasurement extends BaseMeasurement
{
    public const CODE = 'users.new';

    public const GROUP_SOURCE = 'source';
    public const GROUP_REGISTRATION_CHANNEL = 'registration_channel';
    public const GROUP_SALES_FUNNEL = 'sales_funnel_id';

    protected const GROUPS = [
        self::GROUP_SOURCE,
        self::GROUP_REGISTRATION_CHANNEL,
        self::GROUP_SALES_FUNNEL,
    ];

    public function calculate(Criteria $criteria): Series
    {
        $series = $criteria->getEmptySeries();

        foreach ($this->groups() as $group) {
            $fields = $criteria->getAggregation()->select('users.created_at');
            if ($group) {
                $fields[] = $group;
            }
            $fieldsString = implode(',', $fields);

            $query = "
                SELECT {$fieldsString}, COUNT(*) AS count
                FROM users
                WHERE ?
                GROUP BY {$criteria->getAggregation()->group($fields)}
                ORDER BY {$criteria->getAggregation()->group($fields)}
            ";

            $result = $this->db()->query(
                $query,
                [
                    'created_at >=' => $criteria->getFrom(),
                    'created_at <' => $criteria->getTo(),
                ],
            );

            $rows = $result->fetchAll();
            foreach ($rows as $row) {
                $point = new Point(
                    $criteria->getAggregation(),
                    $row->count,
                    DateData::fromRow($row)->getDateTime(),
                    $group ? $row->{$group} : null,
                );
                if ($group) {
                    $series->setGroupPoint($group, $point);
                } else {
                    $series->setPoint($point);
                }
            }
        }

        return $series;
    }
}
