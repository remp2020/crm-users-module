<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Graphs\Scale\Measurements\RangeScaleFactory;
use Crm\UsersModule\Measurements\NewUsersMeasurement;
use Crm\UsersModule\Measurements\SignInMeasurement;
use Nette\Application\Attributes\Persistent;
use Nette\Utils\DateTime;

class DashboardPresenter extends AdminPresenter
{
    #[Persistent]
    public $dateFrom;

    #[Persistent]
    public $dateTo;

    public function startup()
    {
        parent::startup();
        $this->dateFrom = $this->dateFrom ?? DateTime::from('-2 months')->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $this->template->dateFrom = $this->dateFrom;
        $this->template->dateTo = $this->dateTo;
    }

    public function createComponentDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentGoogleUserRegistrationsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.users.registration.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.registration.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        $criteria = (new Criteria)
            ->setSeries(NewUsersMeasurement::CODE)
            ->setGroupBy(NewUsersMeasurement::GROUP_SOURCE)
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT);

        $control->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentGoogleUserRegistrationsStatsPerSalesFunnelGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.users.registration_from_funnel.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.registration_from_funnel.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        $criteria = (new Criteria)
            ->setSeries(NewUsersMeasurement::CODE)
            ->setGroupBy(NewUsersMeasurement::GROUP_SALES_FUNNEL)
            ->setWhere('`measurement_group_values`.`key` IS NOT NULL') // filter out registrations without funnel
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT);

        $control->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentGoogleUserDisabledGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('users')
            ->setTimeField('modified_at')
            ->setWhere("AND active = 0 AND confirmed_at IS NULL")
            ->setValueField('COUNT(*)')
            ->setStart(DateTime::from($this->dateFrom))
            ->setEnd(DateTime::from($this->dateTo)));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.users.disabled.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.disabled.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleLogginAttemptsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
//        $this->getSession()->close();

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.logins.total.title'))
            ->setGraphHelp($this->translator->translate('dashboard.logins.total.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        $criteria = (new Criteria)
            ->setSeries(SignInMeasurement::CODE)
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.logins.total.title'));

        $control->addGraphDataItem($graphDataItem);

        return $control;
    }
}
