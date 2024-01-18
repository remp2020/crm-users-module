<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Selection;

interface FilterUserActionLogsDataProviderInterface extends DataProviderInterface
{
    /**
     * @return Selection
     */
    public function provide(array $params): Selection;
}
