<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Models\Snippet\SnippetRenderer;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Router\RedirectValidator;
use Crm\UsersModule\Events\UserSignOutEvent;
use Crm\UsersModule\Models\Auth\Authorizator;
use Crm\UsersModule\Models\Auth\InvalidEmailException;
use Crm\UsersModule\Models\Auth\SignInRedirectValidator;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SignPresenter extends FrontendPresenter
{
    #[Persistent]
    public $back;

    private string $referer;

    private $signInRedirect;

    public function __construct(
        private Authorizator $authorizator,
        private UserManager $userManager,
        private SnippetRenderer $snippetRenderer,
        private RedirectValidator $redirectValidator,
        // temporary injection to make @deprecated SignInRedirectValidator work, will be removed
        private SignInRedirectValidator $signInRedirectValidator
    ) {
        parent::__construct();
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
    protected function createComponentSignInForm(): Form
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->addText('username', $this->translator->translate('users.frontend.sign_in.username.label'))
            ->setHtmlType('email')
            ->setHtmlAttribute('autofocus')
            ->setRequired($this->translator->translate('users.frontend.sign_in.username.required'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.username.placeholder'));

        $form->addPassword('password', $this->translator->translate('users.frontend.sign_in.password.label'))
            ->setRequired($this->translator->translate('users.frontend.sign_in.password.required'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.password.required'));

        $form->addCheckbox('remember', $this->translator->translate('users.frontend.sign_in.remember'));

        $form->addHidden('redirect', $this->signInRedirect);

        $form->addSubmit('send', $this->translator->translate('users.frontend.sign_in.submit'));

        $form->setDefaults([
            'remember' => true,
        ]);

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function renderIn($url = null)
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('signInCallback', $url);
        }

        $this->signInRedirect = $url;
        $this->template->signInRedirect = $url;
    }

    public function signInFormSucceeded($form, $values)
    {
        if ($values->remember) {
            $this->getUser()->setExpiration('14 days');
        } else {
            $this->getUser()->setExpiration('20 minutes', true);
        }

        try {
            $this->getUser()->login(['username' => $values->username, 'password' => $values->password]);

            $this->getUser()->setAuthorizator($this->authorizator);

            if ($this->getParameter('back') !== null) {
                $this->restoreRequest($this->getParameter('back'));
            }

            $this->redirect('signInCallback', $values->redirect);
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
        }
    }

    public function actionOut()
    {
        $this->emitter->emit(new UserSignOutEvent($this->getUser()));

        $this->getUser()->logout();

        $this->flashMessage($this->translator->translate('users.frontend.sign_in.signed_out'));
        if ($this->getParameter('back') !== null) {
            $this->restoreRequest($this->getParameter('back'));
        }

        $this->redirect('in');
    }

    public function renderUp()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('signInCallback');
        }
    }

    protected function createComponentSignUpForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addText('username', 'users.frontend.sign_up.username.label')
            ->setHtmlType('email')
            ->setHtmlAttribute('autofocus')
            ->setRequired('users.frontend.sign_up.username.required')
            ->setHtmlAttribute('placeholder', 'users.frontend.sign_up.username.placeholder');

        $password = $form->addPassword('password', 'users.frontend.sign_up.password.label')
            ->setHtmlAttribute('placeholder', 'users.frontend.sign_up.password.placeholder');
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

        $this->redirect('signInCallback', $referer);
    }

    public function actionSignInCallback($redirectUrl = null)
    {
        if ($redirectUrl && $this->redirectValidator->isAllowed($redirectUrl)) {
            $this->redirectUrl($redirectUrl);
        } else {
            $this->redirect($this->homeRoute);
        }
    }
}
