<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;

interface GoogleSignInDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): void;
}
