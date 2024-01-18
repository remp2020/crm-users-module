<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

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
