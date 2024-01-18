<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\Selection;

/**
 * FilterUsersSelectionDataProviderInterface is responsible for enhancing provided selection with additional conditions
 *
 * Interface FilterUsersFormDataProviderInterface
 * @package Crm\UsersModule\DataProvider
 */
interface FilterUsersSelectionDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type Selection $selection
     *   @type array $params
     * }
     * @return Selection
     */
    public function provide(array $params): Selection;
}
