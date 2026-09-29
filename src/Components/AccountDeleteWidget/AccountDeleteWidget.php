<?php

namespace Crm\UsersModule\Components\AccountDeleteWidget;

use Crm\ApplicationModule\Models\User\DeleteUserData;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\UsersModule\Forms\UserDeleteFormFactory;
use Nette\Forms\Form;
use Nette\Localization\Translator;
use Nette\Security\User;

/**
 * Account deletion section of the user settings page (password confirmed).
 */
class AccountDeleteWidget extends BaseLazyWidget
{
    private string $templateName = 'account_delete_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private DeleteUserData $deleteUserData,
        private User $user,
        private Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function render()
    {
        if (!$this->user->isLoggedIn()) {
            return;
        }

        [$this->template->canBeDeleted, $_] = $this->deleteUserData->canBeDeleted($this->user->getId());
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function createComponentUserDeleteForm(UserDeleteFormFactory $factory)
    {
        $form = $factory->create($this->user->getId());
        $form->onError[] = function (Form $form) {
            $this->getPresenter()->flashMessage($form->getErrors()[0], 'error');
        };

        $factory->onSuccess = function () {
            $this->user->logout(true);
            $this->getPresenter()->flashMessage($this->translator->translate('users.frontend.settings.account_delete.success'));
            $this->getPresenter()->redirect(':Users:Sign:In');
        };

        return $form;
    }
}
