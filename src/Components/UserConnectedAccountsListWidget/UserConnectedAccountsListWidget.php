<?php

namespace Crm\UsersModule\Components\UserConnectedAccountsListWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Localization\Translator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class UserConnectedAccountsListWidget extends BaseLazyWidget
{
    private string $templateName = 'user_connected_accounts_list_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private UsersRepository $usersRepository,
        private UserConnectedAccountsRepository $userConnectedAccountsRepository,
        private Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function identifier()
    {
        return 'userconnectedaccountslistwidget';
    }

    public function render($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new BadRequestException();
        }
        $connectedAccounts = $this->userConnectedAccountsRepository->getTable()->where('user_id', $user->id)->fetchAll();
        $this->template->connectedAccounts = $connectedAccounts;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleDisconnect($id)
    {
        $userConnectedAccount = $this->userConnectedAccountsRepository->getTable()->where(['id' => $id])->fetch();
        if ($userConnectedAccount) {
            $this->userConnectedAccountsRepository->removeAccountForUser($userConnectedAccount->user, $id);
            $this->presenter->flashMessage($this->translator->translate('users.admin.user_connected_accounts_list_widget.flash_message'));
        }
        $this->presenter->redirect('this');
    }
}
