<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;

/**
 * FilterUsersFormDataProviderInterface is responsible for enhancing provided form with additional form elements
 *
 * Interface FilterUsersFormDataProviderInterface
 * @package Crm\UsersModule\DataProvider
 */
interface FilterUsersFormDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type Form $form
     * }
     * @return Form
     */
    public function provide(array $params): Form;

    /**
     * @param Selection $selection
     * @param array $formData Form values parsed by AdminFilterFormData.
     * @return Selection
     */
    public function filter(Selection $selection, array $formData): Selection;
}
