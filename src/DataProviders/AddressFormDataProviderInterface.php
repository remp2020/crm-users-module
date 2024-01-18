<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface AddressFormDataProviderInterface extends DataProviderInterface
{
    /***
     * @param array $params {
     *   @type Form $form
     *   @type string $addressType If form supports multiple types then omit and use `type` select field in the form.
     *   @type string $container Name of the container in form in which fields will be used. Omit if there is no container in the form.
     * }
     * @return Form
     */
    public function provide(array $params): Form;
}
