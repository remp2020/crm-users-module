<?php

namespace Crm\UsersModule\Components\MonthUsersSmallBarGraphWidget;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraph\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphData;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Nette\Localization\Translator;

/**
 * This widget fetches data of new created users for each day
 * of the last month and renders simple google graph.
 *
 * @package Crm\UsersModule\Components
 */
class MonthUsersSmallBarGraphWidget extends BaseLazyWidget
{
    private $templateName = 'month_users_small_bar_graph_widget.latte';

    private $factory;

    private $graphData;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SmallBarGraphControlFactoryInterface $factory,
        GraphData $graphData,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->factory = $factory;
        $this->graphData = $graphData;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'monthuserssmallbargraphwidget';
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    protected function createComponentUsersSmallBarGraph()
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem
            ->setCriteria(
                (new Criteria())
                    ->setStart('-31 days')
                    ->setTableName('users')
            );

        $this->graphData->addGraphDataItem($graphDataItem);
        $this->graphData->setScaleRange('day');

        $control = $this->factory->create();
        $control->setGraphTitle($this->translator->translate('users.month_users_small_bar_graph_widget.title'))
            ->addSerie($this->graphData->getData());
        return $control;
    }
}
