<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface ClaimUserDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{unclaimedUser: ActiveRow, loggedUser: ActiveRow} $params
     */
    public function provide(array $params): void;
}
