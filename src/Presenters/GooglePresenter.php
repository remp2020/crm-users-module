<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Router\RedirectValidator;
use Crm\UsersModule\Models\Auth\Sso\AdminAccountSsoLinkingException;
use Crm\UsersModule\Models\Auth\Sso\AlreadyLinkedAccountSsoException;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Models\Auth\Sso\SsoException;
use Nette\Application\BadRequestException;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tracy\Debugger;

class GooglePresenter extends FrontendPresenter
{
    private const SESSION_SECTION = 'google_presenter';

    public function __construct(
        private GoogleSignIn $googleSignIn,
        private RedirectValidator $redirectValidator,
    ) {
        parent::__construct();
    }

    public function actionSign()
    {
        if (!$this->googleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        unset(
            $session->finalUrl,
            $session->referer,
            $session->back,
            $session->locale
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
            // redirect URL is your.crm.url/users/google/callback
            $authUrl = $this->googleSignIn->signInRedirect($this->link('//callback'), $source);
            // redirect user to google authentication
            $this->redirectUrl($authUrl);
        } catch (SsoException $e) {
            Debugger::log($e->getMessage(), Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.google.fail'));
            $this->redirect('Sign:in');
        }
    }

    public function actionCallback()
    {
        if (!$this->googleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        $referer = $session->referer ?? null;
        $locale = $session->locale ?? null;

        try {
            $user = $this->googleSignIn->signInCallback($this->link('//callback'), $referer, $locale);

            if (!$this->getUser()->isLoggedIn()) {
                // AutoLogin will log in user - create access token and set user flag (in session) to authenticated
                $this->getUser()->login([
                    'user' => $user,
                    'autoLogin' => true,
                    'source' => GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO,
                ]);
            }
        } catch (SsoException $e) {
            Debugger::log($e, Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.google.fail'), 'error');
            $this->redirect('Users:settings');
        } catch (AlreadyLinkedAccountSsoException $e) {
            $this->flashMessage($this->translator->translate('users.frontend.google.used_account', [
                'email' => $e->getEmail(),
            ]), 'error');
            $this->redirect('Users:settings');
        } catch (AdminAccountSsoLinkingException $e) {
            $this->flashMessage($this->translator->translate('users.frontend.google.link_disabled_for_admin'), 'error');
            $this->redirect('Users:settings');
        }

        $back = $session->back;
        $finalUrl = $session->finalUrl;

        if ($back) {
            $this->restoreRequest($back);
        }

        if ($finalUrl) {
            $this->redirectUrl($finalUrl);
        } else {
            $this->redirect($this->homeRoute);
        }
    }

    public function actionCodeSignIn()
    {
        if (!$this->googleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $referer = $this->getParameter('referer');
        $back = $this->getParameter('back');
        $locale = $this->getParameter('locale');
        $source = $this->getParameter('source');
        $finalUrl = $this->getParameter('url');
        $code = $this->getParameter('code');

        if (!$code) {
            $this->error("Empty 'code' parameter", IResponse::S400_BAD_REQUEST);
        }

        try {
            $user = $this->googleSignIn->signInUsingCode(
                code: $code,
                referer: $referer,
                locale: $locale,
                source: $source,
            );

            if (!$this->getUser()->isLoggedIn()) {
                // AutoLogin will log in user - create access token and set user flag (in session) to authenticated
                $this->getUser()->login([
                    'user' => $user,
                    'autoLogin' => true,
                    'source' => GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO,
                ]);
            }
        } catch (SsoException $e) {
            Debugger::log($e, Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.google.fail'), 'error');
            $this->redirect('Users:settings');
        } catch (AlreadyLinkedAccountSsoException $e) {
            $this->flashMessage($this->translator->translate('users.frontend.google.used_account', [
                'email' => $e->getEmail(),
            ]), 'error');
            $this->redirect('Users:settings');
        }

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
