<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Snippet\SnippetRenderer;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\UserSignOutEvent;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SignPresenter extends FrontendPresenter
{
    private $authorizator;

    private $userManager;

    private $snippetRenderer;

    private $referer;

    /** @persistent */
    public $back;

    public function __construct(
        Authorizator $authorizator,
        UserManager $userManager,
        SnippetRenderer $snippetRenderer
    ) {
        parent::__construct();
        $this->authorizator = $authorizator;
        $this->userManager = $userManager;
        $this->snippetRenderer = $snippetRenderer;
    }

    public function startup()
    {
        parent::startup();

        $refererUrl = $this->request->getReferer();
        $this->referer = '';

        if ($refererUrl) {
            $this->referer = $refererUrl->__toString();
        }

        if ($this->request->getQuery('referer')) {
            $this->referer = $this->request->getQuery('referer');
        }
    }

    /**
     * Sign-in form factory.
     * @return Form
     */
    protected function createComponentSignInForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->addText('username', $this->translator->translate('users.frontend.sign_in.username.label'))
            ->setType('email')
            ->setAttribute('autofocus')
            ->setRequired($this->translator->translate('users.frontend.sign_in.username.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.username.placeholder'));

        $form->addPassword('password', $this->translator->translate('users.frontend.sign_in.password.label'))
            ->setRequired($this->translator->translate('users.frontend.sign_in.password.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.password.required'));

        $form->addCheckbox('remember', $this->translator->translate('users.frontend.sign_in.remember'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.sign_in.submit'));

        $form->setDefaults([
            'remember' => true,
        ]);

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function renderIn()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect($this->homeRoute);
        }
    }

    public function signInFormSucceeded($form, $values)
    {
        if ($values->remember) {
            $this->getUser()->setExpiration('14 days', false);
        } else {
            $this->getUser()->setExpiration('20 minutes', true);
        }

        try {
            $this->getUser()->login(['username' => $values->username, 'password' => $values->password]);

            $this->getUser()->setAuthorizator($this->authorizator);

            $session = $this->getSession('success_login');
            $session->success = 'success';

            $this->restoreRequest($this->getParameter('back'));
            $this->redirect($this->homeRoute);
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
        }
    }

    public function actionOut()
    {
        $this->emitter->emit(new UserSignOutEvent($this->getUser()));

        $this->getUser()->logout();

        $this->flashMessage($this->translator->translate('users.frontend.sign_in.signed_out'));
        $this->restoreRequest($this->getParameter('back'));

        if (!$this->response->getHeader('Location')) {
            $this->redirect('in');
        }
    }

    public function renderUp()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect($this->homeRoute);
        }
    }

    protected function createComponentSignUpForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addText('username', 'users.frontend.sign_up.username.label')
            ->setType('email')
            ->setAttribute('autofocus')
            ->setRequired('users.frontend.sign_up.username.required')
            ->setAttribute('placeholder', 'users.frontend.sign_up.username.placeholder');

        $password = $form->addPassword('password', 'users.frontend.sign_up.password.label')
            ->setAttribute('placeholder', 'users.frontend.sign_up.password.placeholder');
        $exists = false;
        if ($this->request->getPost('username')) {
            $exists = $this->userManager->loadUserByEmail($this->request->getPost('username'));
        }
        if (!$exists) {
            $password->setOption('class', 'hidden');
        }


        $snippet = $this->snippetRenderer->render('terms-of-use-form');
        if ($snippet) {
            $form->addCheckbox('toc', Html::el()->setHtml($snippet))
                ->setRequired('users.frontend.sign_up.toc.required');
        }

        $form->addHidden('redirect', $this->referer);

        $form->addSubmit('send', 'users.frontend.sign_up.submit');

        $form->onSuccess[] = [$this, 'signUpFormSucceeded'];
        return $form;
    }

    public function signUpFormSucceeded($form, $values)
    {
        if ($this->userManager->loadUserByEmail($values->username) && !$values->password) {
            $form->addError('users.frontend.sign_up.error.already_registered');
            return;
        }

        $referer = null;
        if (isset($values->redirect) && $values->redirect) {
            $referer = $values->redirect;
        }

        if ($values->password) {
            try {
                $this->getUser()->login(['username' => $values->username, 'password' => $values->password]);
            } catch (AuthenticationException $exp) {
                $form->addError($exp->getMessage());
                return;
            }

            $this->userManager->loadUserByEmail($values->username);
        } else {
            try {
                $user = $this->userManager->addNewUser($values->username, true, 'signup-form', $referer);
            } catch (InvalidEmailException $e) {
                $form->addError('users.frontend.sign_up.error.invalid_email');
                return;
            }
            $this->getUser()->login(['user' => $user, 'autoLogin' => true]);
        }

        if ($referer) {
            $this->redirectUrl($referer);
        } else {
            $this->redirect($this->homeRoute);
        }
    }
}
