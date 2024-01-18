<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Selection;

interface FilterAbusiveUserFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);

    public function filter(Selection $selection, array $params): Selection;
}
