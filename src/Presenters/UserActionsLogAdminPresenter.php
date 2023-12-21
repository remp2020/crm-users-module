<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\UsersModule\Components\UserActionLogAdminFactoryInterface;
use Crm\UsersModule\DataProvider\FilterUserActionLogsDataProviderInterface;
use Crm\UsersModule\DataProvider\FilterUserActionLogsFormDataProviderInterface;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UserActionsLogAdminPresenter extends AdminPresenter
{
    private $userActionsLogRepository;

    private $dataProviderManager;

    #[Persistent]
    public $logAction;

    #[Persistent]
    public $subscriptionTypeId;

    public function __construct(
        UserActionsLogRepository $userActionsLogRepository,
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct();
        $this->userActionsLogRepository = $userActionsLogRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $this->template->logs = $this->userActionsLogRepository->all();
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $this->template->logAction = $id;
        $this->template->logs = $this->userActionsLogRepository->all()->where(['action' => $id]);
    }

    public function createComponentUserActionsLog(UserActionLogAdminFactoryInterface $userActionLogAdminFactory)
    {
        $control = $userActionLogAdminFactory->create();
        $control->setData($this->params);
        return $control;
    }

    protected function createComponentLogGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $where = '';
        if ($this->params['id']) {
            $where = "AND action='".addslashes($this->params['id'])."'";
        }

        $graphDataItem1 = new GraphDataItem();
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('user_actions_log')
            ->setTimeField('created_at')
            ->setWhere($where)
            ->setValueField('COUNT(*)')
            ->setStart('-1 month'))
            ->setName('Logs');

        $control = $factory->create()
            ->setGraphTitle('Logs')
            ->setGraphHelp('Logs')
            ->addGraphDataItem($graphDataItem1);

        return $control;
    }

    protected function createComponentFilterForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapInlineRenderer());
        $counts = $this->userActionsLogRepository->totalCounts();
        $form->addSelect('logAction', $this->translator->translate('users.admin.user_actions_log.logaction.label'), $counts->fetchPairs('action', 'action'))->setPrompt('--');

        /** @var FilterUserActionLogsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_user_actions_log_form', FilterUserActionLogsFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->addSubmit('filter', $this->translator->translate('users.admin.user_actions_log.submit'));
        $form->setDefaults($this->params);
        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        return $form;
    }
}
