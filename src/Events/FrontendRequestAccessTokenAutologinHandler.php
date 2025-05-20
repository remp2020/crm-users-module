<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Events\FrontendRequestEvent;
use Crm\UsersModule\Authenticator\AccessTokenAuthenticator;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\User;

class FrontendRequestAccessTokenAutologinHandler extends AbstractListener
{
    private Translator $translator;
    private User $user;
    private Request $httpRequest;
    private Response $httpResponse;
    private AccessTokenAuthenticator $accessTokenAuthenticator;

    public function __construct(
        Translator $translator,
        User $user,
        Request $httpRequest,
        Response $httpResponse,
        AccessTokenAuthenticator $accessTokenAuthenticator,
    ) {
        $this->translator = $translator;
        $this->user = $user;
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
        $this->accessTokenAuthenticator = $accessTokenAuthenticator;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof FrontendRequestEvent) {
            throw new \Exception('Invalid type of event received, ' . self::class . ' expected: ' . get_class($event));
        }
        if ($this->user->isLoggedIn()) {
            return;
        }
        $accessToken = $this->httpRequest->getCookie('n_token');
        if (!$accessToken) {
            return;
        }

        // Check if token is disabled, because otherwise each request attempting log-in will reset SESSION ID
        if ($this->accessTokenAuthenticator->isDisabledForToken($accessToken)) {
            return;
        }

        try {
            $this->user->login(['accessToken' => $accessToken]);
            // Do not refresh POST/PUT requests (otherwise data will get lost)
            if (!in_array($this->httpRequest->getMethod(), ['POST', 'PUT'], true) && $this->user->isLoggedIn()) {
                $this->httpResponse->addHeader('Refresh', 0);
                exit;
            }
        } catch (AuthenticationException $e) {
            if ($e->getCode() === Authenticator::NOT_APPROVED) {
                $event->addFlashMessages(
                    $this->translator->translate('users.authenticator.access_token.autologin_disabled'),
                    'notice',
                );
            }
        }
    }
}
