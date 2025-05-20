<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Models\User\DeleteUserData;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Repositories\UserActionsLogRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Security\User;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AdminUserDeleteFormFactory
{
    public const USER_ACTIONS_LOG_ACTION = 'anonymize_user.forced';

    private DeleteUserData $deleteUserData;

    private UserActionsLogRepository $userActionsLogRepository;

    private UsersRepository $usersRepository;

    private User $adminUser;

    private Translator $translator;

     /* callback function */
    public $onSubmit;

    /* callback function */
    public $onError;

    public function __construct(
        DeleteUserData $deleteUserData,
        UserActionsLogRepository $userActionsLogRepository,
        UsersRepository $usersRepository,
        User $adminUser,
        Translator $translator,
    ) {
        $this->deleteUserData = $deleteUserData;
        $this->userActionsLogRepository = $userActionsLogRepository;
        $this->usersRepository = $usersRepository;
        $this->adminUser = $adminUser;
        $this->translator = $translator;
    }

    public function create(ActiveRow $user): Form
    {
        $form = new Form;

        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        // use inline render; otherwise lone button is weirdly aligned to left
        $form->setRenderer(new BootstrapInlineRenderer());

        [$canBeDeleted, $errors] = $this->deleteUserData->canBeDeleted($user->id);
        if (!$canBeDeleted) {
            // use standard rendered for form with text area
            $form->setRenderer(new BootstrapRenderer());

            $cannotBeDeletedReason = $errors ? implode(PHP_EOL, $errors) : '';
            $form->addTextArea('reason', $this->translator->translate('users.admin.delete_user_admin_form.reason.label'))
                ->setOption('description', Html::el('span', ['class' => 'help-block'])->setHtml(
                    "<i>System: [{$cannotBeDeletedReason}]</i><br><br>" . $this->translator->translate('users.admin.delete_user_admin_form.reason.required'),
                ))
                ->setHtmlId('reason')
                ->setRequired($this->translator->translate('users.admin.delete_user_admin_form.reason.required'));
        }

        $form->addSubmit('send')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('onclick', "if (Nette.validateForm(this.form, true)) $(this).button('loading');")
            ->setAttribute('data-loading-text', $this->translator->translate('users.admin.delete_user_admin_form.sending'))
            ->setHtml('<i class="fa fa-trash"></i> ' . $this->translator->translate('users.admin.delete_user_admin_form.button', ['email' => $user->email]));

        $form->addHidden('user_id', $user->id);
        $form->onSubmit[] = [$this, 'formSucceeded'];
        $form->onRender[] = function (Form $form) {
            $formId = $form->getElementPrototype()->getAttribute('id');
            echo "<style>
              #{$formId}.form-inline .form-group {
                  width: 100%;
                  text-align: center;
              }
            </style>";
        };
        return $form;
    }

    public function formSucceeded(Form $form)
    {
        $values = $form->getValues();
        $reason = $values['reason'] ?? null;
        $userId = $values['user_id'] ?? null;

        $user = $userId ? $this->usersRepository->find($userId) : null;
        if ($user === null) {
            $form->addError($this->translator->translate('users.admin.delete_user_admin_form.errors.user_not_found', ['user_id' => $userId]));
            $this->onError->__invoke();
            return;
        }

        // reason is required when user's account doesn't fulfill all requirements for removal
        list($canBeDeleted) = $this->deleteUserData->canBeDeleted($user->id);
        if (!$canBeDeleted) {
            if ($reason === null || empty(trim($reason))) {
                $form->addError($this->translator->translate('users.admin.delete_user_admin_form.reason.required'));
                $this->onError->__invoke();
                return;
            }
            // store into user actions reason of anonymization and ID of administrator
            $this->userActionsLogRepository->add($user->id, self::USER_ACTIONS_LOG_ACTION, ['reason' => $reason, 'admin_id' => $this->adminUser->getIdentity()->id]);
        }

        $this->deleteUserData->deleteData($user->id, true);

        $user = $this->usersRepository->find($user->id);
        $this->usersRepository->update($user, ['note' => $this->translator->translate('users.deletion_note.admin_deleted_account')]);

        $this->onSubmit->__invoke($user->id);
    }
}
