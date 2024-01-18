<?php

namespace Crm\UsersModule\Measurements;

use Crm\ApplicationModule\Models\Measurements\Aggregation\DateData;
use Crm\ApplicationModule\Models\Measurements\BaseMeasurement;
use Crm\ApplicationModule\Models\Measurements\Criteria;
use Crm\ApplicationModule\Models\Measurements\Point;
use Crm\ApplicationModule\Models\Measurements\Series;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;

class SignInMeasurement extends BaseMeasurement
{
    public const CODE = 'users.sign_in';

    public function calculate(Criteria $criteria): Series
    {
        $fields = $criteria->getAggregation()->select('login_attempts.created_at');
        $fieldsString = implode(',', $fields);

        $query = "
            SELECT {$fieldsString}, COUNT(*) AS count
            FROM login_attempts
            WHERE ?
              GROUP BY {$criteria->getAggregation()->group($fields)}
                ORDER BY {$criteria->getAggregation()->group($fields)}
        ";

        $series = $criteria->getEmptySeries();

        $result = $this->db()->query(
            $query,
            [
                'created_at >=' => $criteria->getFrom(),
                'created_at <' => $criteria->getTo(),
                'status' => LoginAttemptsRepository::STATUS_OK,
            ],
        );
        $rows = $result->fetchAll();
        foreach ($rows as $row) {
            $point = new Point($criteria->getAggregation(), $row->count, DateData::fromRow($row)->getDateTime());
            $series->setPoint($point);
        }

        return $series;
    }
}
