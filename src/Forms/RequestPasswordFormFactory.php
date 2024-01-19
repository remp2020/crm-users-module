<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RequestPasswordFormFactory
{
    private $applicationConfig;

    private $userManager;

    private $translator;

    /* callback function */
    public $onSuccess;

    public function __construct(
        ApplicationConfig $applicationConfig,
        UserManager $userManager,
        Translator $translator
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->userManager = $userManager;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create()
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $form->addText('email', 'users.frontend.request_password.email.label')
            ->setHtmlType('email')
            ->setHtmlAttribute('autofocus')
            ->setRequired('users.frontend.request_password.email.required')
            ->setHtmlAttribute('placeholder', 'users.frontend.request_password.email.placeholder')
            ->addRule(
                function (TextInput $input) {
                    $userRow = $this->userManager->loadUserByEmail($input->getValue());
                    if ($userRow) {
                        return (bool)$userRow->active;
                    }
                    return true;
                },
                $this->translator->translate(
                    'users.frontend.request_password.inactive_user',
                    ['contactEmail' => $this->applicationConfig->get('contact_email')]
                )
            );

        $form->addSubmit('send', 'users.frontend.request_password.submit');

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $result = $this->userManager->requestResetPassword($values->email);

        if (!$result) {
            $form['email']->addError($this->translator->translate('users.frontend.request_password.invalid_email'));
        } else {
            $this->onSuccess->__invoke($values->email);
        }
    }
}
