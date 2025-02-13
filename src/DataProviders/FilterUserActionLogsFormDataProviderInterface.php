<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;

interface FilterUserActionLogsFormDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type Form $form
     * }
     * @return Form
     */
    public function provide(array $params): Form;
}
