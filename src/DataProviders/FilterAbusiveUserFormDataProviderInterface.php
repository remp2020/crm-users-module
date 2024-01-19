<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Models\Database\Selection;

interface FilterAbusiveUserFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);

    public function filter(Selection $selection, array $params): Selection;
}
