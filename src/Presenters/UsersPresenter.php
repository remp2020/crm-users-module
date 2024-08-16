<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Helpers\SecuredAdminAccess;
use Crm\ApplicationModule\LatteFunctions\EscapeHTML;
use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\User\DeleteUserData;
use Crm\ApplicationModule\Models\User\DownloadUserData;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\UserResetPasswordSuccessEvent;
use Crm\UsersModule\Events\UserSignOutEvent;
use Crm\UsersModule\Forms\ChangePasswordFormFactory;
use Crm\UsersModule\Forms\RequestPasswordFormFactory;
use Crm\UsersModule\Forms\ResetPasswordFormFactory;
use Crm\UsersModule\Forms\UserDeleteFormFactory;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\ZipBuilder;
use Crm\UsersModule\Repositories\PasswordResetTokensRepository;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Crm\UsersModule\Repositories\UserEmailConfirmationsRepository;
use Nette\Application\Responses\FileResponse;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Nette\Utils\Json;

class UsersPresenter extends FrontendPresenter
{
    public function __construct(
        private ChangePasswordFormFactory $changePasswordFormFactory,
        private DownloadUserData $downloadUserData,
        private DeleteUserData $deleteUserData,
        private RequestPasswordFormFactory $requestPasswordFormFactory,
        private ResetPasswordFormFactory $resetPasswordFormFactory,
        private PasswordResetTokensRepository $passwordResetTokensRepository,
        private ZipBuilder $zipBuilder,
        private UserDeleteFormFactory $userDeleteFormFactory,
        private UserManager $userManager,
        private AccessToken $accessToken,
        private UserEmailConfirmationsRepository $userEmailConfirmationsRepository,
        private GoogleSignIn $googleSignIn,
        private AppleSignIn $appleSignIn,
        private UserConnectedAccountsRepository $userConnectedAccountsRepository,
        private SecuredAdminAccess $securedAdminAccess,
    ) {
        parent::__construct();
    }

    public function renderProfile()
    {
        $this->onlyLoggedIn();
        $this->template->user = $this->getUser();
    }

    public function renderResetPassword($id)
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->emitter->emit(new UserSignOutEvent($this->getUser()));
            $this->getUser()->logout(true);
        }

        if (is_null($id)) {
            $this->redirect('settings');
        }

        if (!$this->passwordResetTokensRepository->isAvailable($id)) {
            $this->flashMessage(
                $this->translator->translate('users.frontend.reset_password.errors.invalid_password_reset_token'),
                "error"
            );
            $this->redirect('settings');
        }
    }

    public function createComponentChangePasswordForm()
    {
        $form = $this->changePasswordFormFactory->create($this->getUser());
        $confirmReset = $this->translator->translate('users.frontend.change_password.actual_password.confirm');

        $form['actual_password']
            ->setOption(
                'description',
                Html::el('span', ['class' => 'help-block', 'onclick' => "return confirm('$confirmReset')"])
                    ->setHtml(
                        $this->translator->translate(
                            'users.frontend.change_password.actual_password.description',
                            ['url' => $this->link('EmailReset!')]
                        )
                    )
            );
        $this->changePasswordFormFactory->onSuccess = function ($devicesLogout = false) {
            if ($devicesLogout) {
                $this->flashMessage($this->translator->translate('users.frontend.change_password.success_with_logout'));
            } else {
                $this->flashMessage($this->translator->translate('users.frontend.change_password.success'));
            }

            $this->redirect($this->homeRoute);
        };
        return $form;
    }

    public function createComponentRequestPasswordForm()
    {
        $form = $this->requestPasswordFormFactory->create();
        $this->requestPasswordFormFactory->onSuccess = function (string $email) {
            $sessionSection = $this->session->getSection('request_password_success');
            $sessionSection->email = $email;
            $this->redirect('requestPasswordSuccessInfo');
        };
        return $form;
    }

    public function createComponentResetPasswordForm()
    {
        $token = '';
        if (isset($this->params['id'])) {
            $token = $this->params['id'];
        }
        $form = $this->resetPasswordFormFactory->create($token);
        $this->resetPasswordFormFactory->onSuccess = function (ActiveRow $token) {
            $this->flashMessage($this->translator->translate('users.frontend.reset_password.success'));

            $this->emitter->emit(new UserResetPasswordSuccessEvent($token->user));

            $this->redirect(':Users:Sign:In', ['url' => $this->link($this->homeRoute)]);
        };
        return $form;
    }

    public function renderSettings()
    {
        $this->template->canBeDeleted = false;
        if ($this->getUser()->isLoggedIn()) {
            [$this->template->canBeDeleted, $_] = $this->deleteUserData->canBeDeleted($this->getUser()->getId());

            $userRow = $this->usersRepository->find($this->getUser()->getId());

            if (!$userRow) {
                throw new \RuntimeException("User with id [{$this->getUser()->getId()}] not found");
            }

            $this->template->appleSignIn = $this->appleSignIn->isEnabled() ?
                $this->link(':Users:Apple:sign', ['url' => $this->link('//this')]) : false;
            $this->template->googleSignIn = $this->googleSignIn->isEnabled() ?
                $this->link(':Users:Google:sign', ['url' => $this->link('//this')]) : false;

            $this->template->appleConnectedAccounts = $this->userConnectedAccountsRepository
                ->getForUser($userRow, UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN)
                ->fetchAll();
            $this->template->googleConnectedAccounts = $this->userConnectedAccountsRepository
                ->getForUser($userRow, UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN)
                ->fetchAll();
        }
    }

    public function handleDownloadData()
    {
        $this->onlyLoggedIn();
        set_time_limit(120);

        $zip = $this->zipBuilder->getZipFile();
        $fileName = $zip->filename;

        // text data
        $userData = $this->downloadUserData->getData($this->getUser()->getId());
        $zip->addFromString('data.json', Json::encode($userData));

        // file attachments
        foreach ($this->downloadUserData->getAttachments($this->getUser()->getId()) as $attachmentName => $attachmentPath) {
            $zip->addFile($attachmentPath, $attachmentName);
        }

        $zip->close();
        clearstatcache();

        $response = new FileResponse($fileName, 'data.zip', 'application/zip', true);
        // Nette appends Content-Range header even when no Range header is present, Varnish doesn't like that
        $response->resuming = false;
        $this->sendResponse($response);
    }

    public function handleDevicesLogout()
    {
        $this->onlyLoggedIn();
        $accessToken = $this->accessToken->getToken($this->getHttpRequest());

        $user = $this->usersRepository->find($this->getUser()->getId());

        $this->userManager->logoutUser($user, [$accessToken]);
        $this->flashMessage($this->translator->translate('users.frontend.settings.devices_logout.success'));
    }

    public function handleUnlinkConnectedAccount(int $accountId)
    {
        $this->onlyLoggedIn();

        $userRow = $this->usersRepository->find($this->getUser()->getId());

        if (!$this->securedAdminAccess->canLinkOrUnlinkAccount($userRow)) {
            $this->flashMessage($this->translator->translate('users.frontend.settings.linked_accounts.unlink_disabled_for_admin'), 'error');
        } else {
            $this->userConnectedAccountsRepository->removeAccountForUser($userRow, $accountId);
            $this->flashMessage($this->translator->translate('users.frontend.settings.linked_accounts.unlink_success'));
        }

        $this->redirect('this');
    }

    public function createComponentUserDeleteForm()
    {
        $form = $this->userDeleteFormFactory->create($this->getUser()->getId());
        $form->onError[] = function (Form $form) {
            $this->flashMessage($form->getErrors()[0], 'error');
        };

        $this->userDeleteFormFactory->onSuccess = function () {
            $this->getUser()->logout(true);
            $this->flashMessage($this->translator->translate('users.frontend.settings.account_delete.success'));
            $this->redirect(':Users:Sign:In');
        };

        return $form;
    }

    public function handleEmailReset()
    {
        $this->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->getUser());
        $newPassword = $this->userManager->resetPassword($user);

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $user,
            'reset_password_with_password',
            [
                'email' => $user->email,
                'password' => $newPassword,
            ]
        ));

        $this->flashMessage($this->translator->translate('users.frontend.change_password.reset_success', [
            'email' => EscapeHTML::escape($user->email)
        ]));
        $this->redirect('this');
    }

    public function renderRequestPasswordSuccessInfo()
    {
        $sessionSection = $this->session->getSection('request_password_success');
        $email = $sessionSection->email;
        unset($sessionSection->email);
        if (!$email) {
            $this->redirect('settings');
        }
        $this->template->email = $email;
    }

    public function renderEmailConfirm(string $token, string $redirectUrl = null)
    {
        $userEmailConfirmation = $this->userEmailConfirmationsRepository->confirm($token);
        if ($userEmailConfirmation) {
            $this->userManager->setEmailValidated($userEmailConfirmation->user, $userEmailConfirmation->confirmed_at);
            $this->userManager->confirmUser($userEmailConfirmation->user);

            if ($redirectUrl) {
                $this->redirectUrl(rawurldecode($redirectUrl));
            }
        }

        $this->template->userEmailConfirmation = $userEmailConfirmation;
    }
}
