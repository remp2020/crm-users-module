<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\FilterUserActionLogsDataProviderInterface;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Nette\Application\UI\Control;

/**
 * This component fetches user actions - filter them through data providers
 * and render bootstrap styled table.
 *
 * @package Crm\UsersModule\Components
 */
class UserActionLogAdmin extends Control
{
    private $templateName = 'user_action_log.latte';

    private $userActionsLogRepository;

    private $dataProviderManager;

    private $data;

    public function __construct(
        UserActionsLogRepository $userActionsLogRepository,
        DataProviderManager $dataProviderManager
    ) {
        $this->userActionsLogRepository = $userActionsLogRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $logs = $this->userActionsLogRepository->all();
        if (isset($this->data['logAction'])) {
            $logs->where(['action' => $this->data['logAction']]);
        }
        if (isset($this->data['userId'])) {
            $logs->where(['user' => $this->data['userId']]);
        }

        $logsRows = $logs->limit(300);

        /** @var FilterUserActionLogsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_user_actions_log_selection', FilterUserActionLogsDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $logs = $provider
                ->provide(['selection' => $logs, 'params' => $this->data]);
        }

        $this->template->totalLogs = $logs->count('*');
        $this->template->logs = $logsRows;
        $this->template->render();
    }
}
