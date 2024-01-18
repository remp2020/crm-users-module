<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\User\DeleteUserData;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;

class UserDeleteFormFactory
{
    private $deleteUserData;

    private $usersRepository;

    private $authenticator;

    private $translator;

    /* callback function */
    public $onSuccess;

    public function __construct(
        UsersRepository $usersRepository,
        DeleteUserData $deleteUserData,
        IAuthenticator $authenticator,
        Translator $translator
    ) {
        $this->deleteUserData = $deleteUserData;
        $this->usersRepository = $usersRepository;
        $this->authenticator = $authenticator;
        $this->translator = $translator;
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
        try {
            $this->authenticator->authenticate(['username' => $user->email, 'password' => $values->password]);
        } catch (AuthenticationException $e) {
            $form->addError($this->translator->translate('users.frontend.settings.account_delete.invalid_password'));
            return;
        }

        list($canBeDeleted, $_) = $this->deleteUserData->canBeDeleted($values->user_id);
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
