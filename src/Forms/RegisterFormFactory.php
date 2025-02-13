<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\DataProviders\RegisterFormDataProviderInterface;
use Crm\UsersModule\Models\Auth\InvalidEmailException;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Localization\Translator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RegisterFormFactory
{
    private $userManager;

    private $dataProviderManager;

    private $user;

    private $translator;

    public $onUserExists;

    public $onUserRegistered;

    public function __construct(
        UserManager $userManager,
        DataProviderManager $dataProviderManager,
        Translator $translator,
        User $user
    ) {
        $this->userManager = $userManager;
        $this->dataProviderManager = $dataProviderManager;
        $this->user = $user;
        $this->translator = $translator;
    }

    public function create()
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addText('email', 'users.form.register.email.label')
            ->setHtmlAttribute('autofocus')
            ->setRequired('users.form.register.email.required')
            ->setHtmlAttribute('placeholder', 'users.form.register.email.placeholder');

        /** @var RegisterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.register_form', RegisterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->addSubmit('submit', 'users.form.register.submit');

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        if ($this->userManager->loadUserByEmail($values->email)) {
            if ($this->onUserExists) {
                ($this->onUserExists)($form, $values);
            }
            $form->addError('users.frontend.sign_up.error.already_registered');
            return;
        }

        try {
            $user = $this->userManager->addNewUser($form->getValues()['email']);
        } catch (InvalidEmailException $e) {
            $form->addError('users.frontend.sign_up.error.invalid_email');
            return;
        }

        $this->user->login(['user' => $user, 'autoLogin' => true]);

        /** @var RegisterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.register_form', RegisterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->submit($user, $form);
        }

        if ($this->onUserRegistered) {
            ($this->onUserRegistered)($form, $values, $user);
        }
    }
}
