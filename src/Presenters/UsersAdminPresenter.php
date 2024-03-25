<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator\PreviousNextPaginator;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\User\DownloadUserData;
use Crm\UsersModule\Components\Widgets\DetailWidget\DetailWidgetFactoryInterface;
use Crm\UsersModule\DataProviders\FilterUsersFormDataProviderInterface;
use Crm\UsersModule\Events\AddressRemovedEvent;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Forms\AdminUserDeleteFormFactory;
use Crm\UsersModule\Forms\AdminUserGroupFormFactory;
use Crm\UsersModule\Forms\UserFormFactory;
use Crm\UsersModule\Forms\UserGroupsFormFactory;
use Crm\UsersModule\Forms\UserNoteFormFactory;
use Crm\UsersModule\Models\AdminFilterFormData;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\ZipBuilder;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CantDeleteAddressException;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserActionsLogRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UsersAdminPresenter extends AdminPresenter
{
    #[Persistent]
    public $formData = [];

    public function __construct(
        private AdminFilterFormData $adminFilterFormData,
        private UsersRepository $usersRepository,
        private UserFormFactory $userFormFactory,
        private GroupsRepository $groupsRepository,
        private UserGroupsFormFactory $userGroupsFormFactory,
        private AdminUserGroupFormFactory $adminUserGroupsFormFactory,
        private AdminUserDeleteFormFactory $adminUserDeleteFormFactory,
        private UserNoteFormFactory $userNoteFormFactory,
        private AddressesRepository $addressesRepository,
        private DataProviderManager $dataProviderManager,
        private UserManager $userManager,
        private ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        private UserActionsLogRepository $userActionsLogRepository,
        private DownloadUserData $downloadUserData,
        private ZipBuilder $zipBuilder,
    ) {
        parent::__construct();
    }

    public function startup()
    {
        parent::startup();
        $this->adminFilterFormData->parse($this->formData);
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $users = $this->adminFilterFormData->getFilteredUsers();

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $users = $users->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($users));

        $this->template->users = $users;
        $this->template->backLink = $this->storeRequest();
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new BadRequestException();
        }
        $this->template->dbUser = $user;
        $this->template->translator = $this->translator;
        $this->template->invoiceAddress = $this->addressesRepository->address($user, 'invoice');
        $this->template->printAddresses = array_filter($this->addressesRepository->addresses($user), function ($item) {
            return $item->type != 'invoice';
        });

        $this->template->lastSuspicious = $this->changePasswordsLogsRepository->lastUserLog($user->id, ChangePasswordsLogsRepository::TYPE_SUSPICIOUS);
        $this->template->canEditRoles = $this->getUser()->isAllowed('Users:AdminGroupAdmin', 'edit');
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new BadRequestException();
        }
        $this->template->dbUser = $user;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew()
    {
    }

    /**
     * @admin-access-level write
     */
    public function handleLogOut($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException("User with id: {$userId} doesn't exist.");
        }

        $abusiveInformationSelection = $this->usersRepository->getAbusiveUsers(
            new DateTime('-1 month'),
            new DateTime(),
            1,
            1,
            'device_count',
            $user->email
        );
        $abusiveInformation = $abusiveInformationSelection->where('users.id = ?', $userId)->fetch();

        $this->userActionsLogRepository->add(
            $userId,
            'users.admin.logout_user',
            [
                'admin_email' => $this->user->getIdentity()->email,
                'active_logins' => $abusiveInformation->token_count ?? 0,
                'active_devices' => $abusiveInformation->device_count ?? 0,
            ]
        );

        $this->userManager->logoutUser($user);

        $this->presenter->flashMessage($this->translator->translate('users.admin.logout_user.all_devices'));
        $this->redirect('show', $userId);
    }

    /**
     * You need to process NotificationEvent in order to
     * send user email containing new password.
     *
     * @admin-access-level write
     *
     * @param $userId
     */
    public function handleResetPassword($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException("User with id: {$userId} doesn't exist.");
        }

        $password = $this->userManager->resetPassword($user, null, false);

        $this->emitter->emit(new NotificationEvent($this->emitter, $user, 'admin_reset_password_with_password', [
            'email' => $user->email,
            'password' => $password
        ]));

        $this->presenter->flashMessage($this->translator->translate('users.admin.reset_password.success'));
        $this->redirect('show', $userId);
    }

    /**
     * @admin-access-level write
     */
    public function handleConfirm($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException("User with id: {$userId} doesn't exist.");
        }

        $this->userManager->confirmUser($user, new DateTime(), true);
        $this->presenter->flashMessage($this->translator->translate('users.admin.confirm.success'));
    }

    public function createComponentUserForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->userFormFactory->create($id);
        $this->userFormFactory->onSave = function ($form, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_form.user_created'));
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->userFormFactory->onUpdate = function ($form, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_form.user_updated'));
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        return $form;
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $mainGroup = $form->addGroup('main')->setOption('label', null);
        $collapseGroup = $form->addGroup('collapse', false)
            ->setOption('container', 'div class="collapse"')
            ->setOption('label', null)
            ->setOption('id', 'formCollapse');
        $buttonGroup = $form->addGroup('button', false)->setOption('label', null);

        $form->addText('text', $this->translator->translate('users.admin.admin_filter_form.text.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.admin_filter_form.text.placeholder'))
            ->setHtmlAttribute('autofocus');
        $form->addText('address', $this->translator->translate('users.admin.admin_filter_form.address.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.admin_filter_form.address.placeholder'));

        $form->setCurrentGroup($collapseGroup);

        $form->addSelect('group', $this->translator->translate('users.admin.admin_filter_form.group.label'), $this->groupsRepository->all()->fetchPairs('id', 'name'))
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);
        $form->addSelect('source', $this->translator->translate('users.admin.admin_filter_form.source.label'), $this->usersRepository->getUserSources())
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        /** @var FilterUsersFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.users_filter_form', FilterUsersFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'formData' => $this->formData]);
        }

        $form->setCurrentGroup($buttonGroup);

        $form->addSubmit('send', $this->translator->translate('users.admin.admin_filter_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('users.admin.admin_filter_form.submit'));
        $presenter = $this;
        $form->addSubmit('cancel', $this->translator->translate('users.admin.admin_filter_form.cancel_filter'))->onClick[] = function () use ($presenter, $form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $presenter->redirect('UsersAdmin:Default', ['formData' => $emptyDefaults]);
        };
        $form->addButton('more')
            ->setHtmlAttribute('data-toggle', 'collapse')
            ->setHtmlAttribute('data-target', '#formCollapse')
            ->setHtmlAttribute('class', 'btn btn-info')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-caret-down"></i> ' . $this->translator->translate('users.admin.admin_filter_form.more'));

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];

        $form->setDefaults($this->adminFilterFormData->getFormValues());

        foreach ($collapseGroup->getControls() as $control) {
            if (!empty($control->getValue())) {
                $collapseGroup->setOption('container', 'div class="collapse in"');
                break;
            }
        }

        return $form;
    }

    public function adminFilterSubmitted($form, $values)
    {
        $this->redirect($this->action, ['formData' => array_map(function ($item) {
            return $item ?: null;
        }, (array)$values)]);
    }

    public function createComponentUserGroupsForm()
    {
        if (!isset($this->params['id'])) {
            return null;
        }

        $form = $this->userGroupsFormFactory->create($this->params['id']);
        $this->userGroupsFormFactory->onAddedUserToGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_added') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->userGroupsFormFactory->onRemovedUserFromGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_removed') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };

        return $form;
    }

    public function createComponentAdminUserGroupsForm()
    {
        if (!isset($this->params['id'])) {
            return null;
        }

        $user = $this->getUser();

        $form = $this->adminUserGroupsFormFactory->create($this->params['id']);
        $this->adminUserGroupsFormFactory->authorize = function () use ($user) {
            return $user->isAllowed('Users:AdminGroupAdmin', 'edit');
        };
        $this->adminUserGroupsFormFactory->onAddedUserToGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_added') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };
        $this->adminUserGroupsFormFactory->onRemovedUserFromGroup = function ($form, $group, $user) {
            $this->flashMessage($this->translator->translate('users.admin.user_groups_form.user_removed') . ' ' . $group->name);
            $this->redirect('UsersAdmin:Show', $user->id);
        };

        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleChangeActivation($userId, $backLink = null)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $this->usersRepository->toggleActivation($user);

        $this->flashMessage($this->translator->translate('users.admin.change_activation.activated'));
        if ($backLink) {
            $this->restoreRequest($backLink);
        }
        $this->redirect('UsersAdmin:Show', $user->id);
    }

    /**
     * @admin-access-level write
     */
    public function handleDeleteUser($id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            throw new BadRequestException();
        }

        $this->payload->isModal = true;
        $this->template->userEmail = $user->email; // this is needed for default view; in modal we know which user to delete after ajax call
        $this->redrawControl('adminUserDeleteFormSnippet');
    }

    protected function createComponentAdminUserDeleteForm()
    {
        $userId = $this->request->getPost('user_id') ?? $this->getParameter('id');

        $user = $this->usersRepository->find($userId);
        if ($user === null) {
            throw new BadRequestException();
        }

        $form = $this->adminUserDeleteFormFactory->create($user);

        $this->adminUserDeleteFormFactory->onSubmit = function ($deletedUserId) {
            $this->flashMessage($this->translator->translate('users.admin.delete_user.deleted'));
            $this->redirect('this');
        };

        $this->adminUserDeleteFormFactory->onError = function () {
            if ($this->isAjax()) {
                $this->redrawControl('adminUserDeleteFormSnippet');
            }
        };

        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleSuspicious($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $abusiveInformationSelection = $this->usersRepository->getAbusiveUsers(
            new DateTime('-1 month'),
            new DateTime(),
            1,
            1,
            'device_count',
            $user->email
        );
        $abusiveInformation = $abusiveInformationSelection->where('users.id = ?', $userId)->fetch();

        $this->userActionsLogRepository->add(
            $userId,
            'users.admin.suspicious_account',
            [
                'admin_email' => $this->user->getIdentity()->email,
                'active_logins' => $abusiveInformation->token_count ?? 0,
                'active_devices' => $abusiveInformation->device_count ?? 0,
            ]
        );

        $this->userManager->suspiciousUser($user);

        $this->flashMessage("OK"); // todo preklady
        $this->redirect('show', $user->id);
    }

    /**
     * @admin-access-level read
     */
    public function renderExport()
    {
        $this->getHttpResponse()->addHeader('Content-Type', 'application/csv');
        $this->getHttpResponse()->addHeader('Content-Disposition', 'attachment; filename=export.csv');

        $this->template->users = $this->adminFilterFormData->getFilteredUsers()->limit(100000);
    }

    protected function createComponentDetailWidget(DetailWidgetFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }

    public function createComponentUserNoteForm()
    {
        $userRow = $this->usersRepository->find($this->params['id']);
        $form = $this->userNoteFormFactory->create($userRow);
        $presenter = $this;
        $this->userNoteFormFactory->onUpdate = function ($form, $user) use ($presenter) {
            $presenter->flashMessage($this->translator->translate('users.admin.user_note_form.note_updated'));
            $presenter->redirect('UsersAdmin:Show', $user->id);
        };
        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleRemoveAddress($addressId)
    {
        $address = $this->addressesRepository->find($addressId);
        try {
            $this->addressesRepository->softDelete($address);
        } catch (CantDeleteAddressException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
            $this->redirect('this');
        }
        $this->emitter->emit(new AddressRemovedEvent($address));
    }

    /**
     * @admin-access-level read
     */
    public function handleDownloadData($userId)
    {
        set_time_limit(120);

        $zip = $this->zipBuilder->getZipFile();
        $fileName = $zip->filename;

        // text data
        $userData = $this->downloadUserData->getData($userId);
        $zip->addFromString('data.json', Json::encode($userData));

        // file attachments
        foreach ($this->downloadUserData->getAttachments($userId) as $attachmentName => $attachmentPath) {
            $zip->addFile($attachmentPath, $attachmentName);
        }

        $zip->close();
        clearstatcache();

        $response = new FileResponse($fileName, 'data.zip', 'application/zip', true);
        // Nette appends Content-Range header even when no Range header is present, Varnish doesn't like that
        $response->resuming = false;
        $this->sendResponse($response);
    }
}
