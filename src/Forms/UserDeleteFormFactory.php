<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Models\User\DeleteUserData;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Models\Auth\UserAuthenticator;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;

class UserDeleteFormFactory
{
    /* callback function */
    public $onSuccess;

    public function __construct(
        private UsersRepository $usersRepository,
        private DeleteUserData $deleteUserData,
        private UserAuthenticator $userAuthenticator,
        private Translator $translator,
    ) {
    }

    /**
     * @return Form
     */
    public function create($userId)
    {
        $form = new Form;

        $form->addProtection();

        $form->addPassword('password', $this->translator->translate('users.frontend.settings.account_delete.password.label'))
            ->setRequired($this->translator->translate('users.frontend.settings.account_delete.password.required'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.settings.account_delete.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-trash"></i> ' . $this->translator->translate('users.frontend.settings.account_delete.submit'));

        $form->addHidden('user_id', $userId);
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    /**
     * @param Form $form
     * @param $values
     */
    public function formSucceeded($form, $values)
    {
        if (!$values->user_id) {
            return;
        }

        $user = $this->usersRepository->find($values->user_id);
        if (!$user) {
            throw new \RuntimeException("User [{$values->user_id}] not found");
        }
        try {
            $this->userAuthenticator->authenticateCredentials([
                'username' => $user->email,
                'password' => $values->password,
            ]);
        } catch (AuthenticationException $e) {
            $form->addError($this->translator->translate('users.frontend.settings.account_delete.invalid_password'));
            return;
        }

        [$canBeDeleted, $_] = $this->deleteUserData->canBeDeleted($values->user_id);
        if (!$canBeDeleted) {
            $form->addError($this->translator->translate('users.frontend.settings.account_delete.cannot_delete'));
            return;
        }

        $this->deleteUserData->deleteData($values->user_id);
        $user = $this->usersRepository->find($values->user_id);
        $this->usersRepository->update($user, ['note' => $this->translator->translate('users.deletion_note.user_deleted_account')]);
        $this->onSuccess->__invoke();
    }
}
