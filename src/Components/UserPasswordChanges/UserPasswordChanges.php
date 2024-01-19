<?php

namespace Crm\UsersModule\Components\UserPasswordChanges;

use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\PasswordResetTokensRepository;
use Nette\Application\UI\Control;

/**
 * This widget fetches user password changes and password change requests
 * and render 2 bootstrap styled tables for both data sets.
 *
 * @package Crm\UsersModule\Components
 */
class UserPasswordChanges extends Control implements WidgetInterface
{
    private $templateName = 'user_password_changes.latte';

    private $changePasswordsLogsRepository;

    private $passwordResetTokensRepository;

    public function __construct(
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        PasswordResetTokensRepository $passwordResetTokensRepository
    ) {
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
    }

    public function header($id = '')
    {
        $header = 'Zmeny hesla';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'userpasswordchanges';
    }

    public function render($id)
    {
        $this->template->changePasswordLogs = $this->changePasswordsLogsRepository->lastUserLogs($id);
        $this->template->totalPasswordChanges = $this->totalCount($id);
        $this->template->passwordResetTokens = $this->passwordResetTokensRepository->userTokens($id);
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->changePasswordsLogsRepository->totalUserLogs($id);
        }
        return $this->totalCount;
    }
}
