<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Router\RedirectValidator;
use Crm\UsersModule\Models\Auth\SignInRedirectValidator;
use Crm\UsersModule\Models\Auth\Sso\AlreadyLinkedAccountSsoException;
use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Models\Auth\Sso\SsoException;
use Nette\Application\BadRequestException;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tracy\Debugger;

class ApplePresenter extends FrontendPresenter
{
    private const SESSION_SECTION = 'apple_presenter';

    public function __construct(
        private AppleSignIn $appleSignIn,
        private RedirectValidator $redirectValidator,
        // temporary injection to make @deprecated SignInRedirectValidator work, will be removed
        private SignInRedirectValidator $signInRedirectValidator
    ) {
        parent::__construct();
    }

    public function actionSign()
    {
        if (!$this->appleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        unset(
            $session->finalUrl,
            $session->referer,
            $session->locale,
            $session->back
        );

        // Final URL destination
        $finalUrl = $this->getParameter('url');
        if ($finalUrl !== null && !is_string($finalUrl)) {
            throw new BadRequestException(
                "Invalid value of 'url' parameter: " . Json::encode($finalUrl),
                IResponse::S400_BAD_REQUEST,
            );
        }
        $referer = $this->getReferer();

        // remove locale from URL; it is already part of final url / referer and it breaks callback URL
        $locale = $this->locale;
        $this->locale = null;

        if ($finalUrl && $this->redirectValidator->isAllowed($finalUrl)) {
            $session->finalUrl = $finalUrl;
        } elseif ($referer && $this->redirectValidator->isAllowed($referer)) {
            // Redirect backup to Referer (if provided 'url' parameter is invalid or manipulated)
            $session->finalUrl = $referer;
        }
        if ($this->getParameter('back')) {
            $session->back = $this->getParameter('back');
        }

        // Save referer
        if ($referer) {
            $session->referer = $referer;
        }
        if ($locale) {
            $session->locale = $locale;
        }

        $source = $this->getParameter('source') ?? $this->getParameter('n_source');

        try {
            // redirect URL is your.crm.url/users/apple/callback
            $authUrl = $this->appleSignIn->signInRedirect($this->link('//callback'), $source);
            // redirect user to apple authentication
            $this->redirectUrl($authUrl);
        } catch (SsoException $e) {
            Debugger::log($e->getMessage(), Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.apple.fail'), 'danger');
            $this->redirect('Sign:in');
        }
    }

    public function actionCallback()
    {
        if (!$this->appleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        $referer = $session->referer ?? null;
        $locale = $session->locale ?? null;

        try {
            $user = $this->appleSignIn->signInCallback($referer, $locale);

            if (!$this->getUser()->isLoggedIn()) {
                // AutoLogin will log in user - create access token and set user flag (in session) to authenticated
                $this->getUser()->login([
                    'user' => $user,
                    'autoLogin' => true,
                    'source' => AppleSignIn::ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO,
                ]);
            }
        } catch (SsoException $e) {
            if ($e->getCode() === SsoException::CODE_CANCELLED) {
                $this->flashMessage($this->translator->translate('users.frontend.apple.cancel'), 'danger');
                $this->redirect('Users:settings');
            }

            Debugger::log($e, Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.apple.fail'), 'danger');
            $this->redirect('Users:settings');
        } catch (AlreadyLinkedAccountSsoException $e) {
            $this->flashMessage($this->translator->translate('users.frontend.apple.used_account', ['email' => $e->getEmail()]), 'danger');
            $this->redirect('Users:settings');
        }

        $finalUrl = $session->finalUrl;
        $back = $session->back;

        if ($back) {
            $this->restoreRequest($back);
        }

        if ($finalUrl) {
            $this->redirectUrl($finalUrl);
        } else {
            $this->redirect($this->homeRoute);
        }
    }
}
