<?php

namespace Crm\UsersModule\Components\MonthToDateUsersStatWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches new users from start of the month to current date
 * and renders simple stat widget + last months value in comparison.
 *
 * @package Crm\UsersModule\Components
 */
class MonthToDateUsersStatWidget extends BaseLazyWidget
{
    private $templateName = 'month_to_date_users_stat_widget.latte';

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
        return 'monthtodateusersstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->count('*');
        $this->template->lastMonthDayUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from('first day of last month 00:00'),
            DateTime::from('-1 month')
        )->count('*');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
