<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Models\Database\Selection;

interface FilterUserActionLogsDataProviderInterface extends DataProviderInterface
{
    /**
     * @return Selection
     */
    public function provide(array $params): Selection;
}
