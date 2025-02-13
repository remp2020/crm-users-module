<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Localization\Translator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapVerticalRenderer;

class ChangePasswordFormFactory
{
    protected $userManager;

    protected $translator;

    /* callback function */
    public $onSuccess;

    /** @var  User */
    private $user;

    private $usersRepository;

    private $accessToken;

    public function __construct(
        UserManager $userManager,
        Translator $translator,
        UsersRepository $usersRepository,
        AccessToken $accessToken
    ) {
        $this->userManager = $userManager;
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->accessToken = $accessToken;
    }

    /**
     * @return Form
     */
    public function create(User $user)
    {
        $form = new Form;
        $this->user = $user;

        $form->setRenderer(new BootstrapVerticalRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addPassword('actual_password', 'users.frontend.change_password.actual_password.label')
            ->setHtmlAttribute('autofocus')
            ->setRequired('users.frontend.change_password.actual_password.required')
            ->setHtmlAttribute('placeholder', 'users.frontend.change_password.actual_password.placeholder');

        $form->addPassword('new_password', 'users.frontend.change_password.new_password.label')
            ->setRequired('users.frontend.change_password.new_password.required')
            ->setHtmlAttribute('placeholder', 'users.frontend.change_password.new_password.placeholder')
            ->addRule(Form::MIN_LENGTH, 'users.frontend.change_password.new_password.minlength', 6);

        $form->addPassword('new_password_confirm', 'users.frontend.change_password.new_password_confirm.label')
            ->setRequired('users.frontend.change_password.new_password_confirm.required')
            ->addRule(Form::EQUAL, 'users.frontend.change_password.new_password_confirm.not_matching', $form['new_password'])
            ->setHtmlAttribute('placeholder', 'users.frontend.change_password.new_password_confirm.placeholder')
            ->setOption('description', 'users.frontend.change_password.new_password_confirm.description');

        $form->addSubmit('send', 'users.frontend.change_password.submit')
            ->onClick[] = [$this, 'formSucceeded'];
        $form->addSubmit('send_and_logout', 'users.frontend.change_password.submit_with_devices_logout')
            ->onClick[] = [$this, 'formSucceededWithLogout'];
        return $form;
    }

    public function formSucceededWithLogout($submitButton, $values)
    {
        $this->formSucceeded($submitButton, $values, true);
    }

    public function formSucceeded($submitButton, $values, $devicesLogout = false)
    {
        $form = $submitButton->getForm();

        if (!$this->user->isLoggedIn()) {
            $form['actual_password']->addError('users.frontend.change_password.errors.could_not_authenticate');
            return false;
        }

        $result = $this->userManager->setNewPassword(
            $this->user->getIdentity()->getId(),
            $values['actual_password'],
            $values['new_password']
        );

        if (!$result) {
            $form['actual_password']->addError('users.frontend.change_password.errors.invalid_credentials');
            return false;
        }

        if ($devicesLogout) {
            $accessToken = $this->accessToken->getToken($form->getPresenter()->getHttpRequest());
            $this->userManager->logoutUser($this->usersRepository->find($this->user->getId()), [$accessToken]);
        }

        $this->onSuccess->__invoke($devicesLogout);
    }
}
