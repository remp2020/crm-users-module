<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\ActiveRow;

interface RegisterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function submit(ActiveRow $User, Form $form): Form;
}
