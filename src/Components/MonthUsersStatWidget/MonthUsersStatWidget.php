<?php

namespace Crm\UsersModule\Components\MonthUsersStatWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches new users this month
 * and renders simple stat widget + last months value in comparison.
 *
 * @package Crm\UsersModule\Components
 */
class MonthUsersStatWidget extends BaseLazyWidget
{
    private $templateName = 'month_users_stat_widget.latte';

    private $usersRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        UsersRepository $usersRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->usersRepository = $usersRepository;
    }

    public function identifier()
    {
        return 'monthusersstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->count('*');
        $this->template->lastMonthUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from('first day of -1 month 00:00'),
            DateTime::from(date('Y-m'))
        )->count('*');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
