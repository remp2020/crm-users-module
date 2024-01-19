<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface CanDeleteAddressDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{address: ActiveRow} $params
     * @return array [
     *   'canDelete' => @type bool,
     *   'message' => @type string (Optional) Use for can't delete messages
     * ]
     * @throws DataProviderException
     */
    public function provide(array $params): array;
}
