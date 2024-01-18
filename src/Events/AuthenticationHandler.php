<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Events\AuthenticationEvent;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;

class AuthenticationHandler extends AbstractListener
{
    public function __construct(
        private UsersRepository $usersRepository,
        private AccessToken $accessToken,
        private AccessTokensRepository $accessTokensRepository,
        private Request $request,
        private Response $response,
        private Translator $translator
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof AuthenticationEvent) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $user = $this->usersRepository->find($event->getUserId());
        if (!$user->active) {
            $this->accessTokensRepository->removeAllUserTokens($user->id);
            $this->accessToken->deleteActualUserToken($user, $this->request, $this->response);
            throw new AuthenticationException($this->translator->translate('users.authenticator.inactive_account'));
        }

        $token = $this->accessToken->getToken($event->getRequest());
        if ($token) {
            $accessToken = $this->accessTokensRepository->loadToken($token);
            if (!$accessToken) {
                $this->accessToken->deleteActualUserToken($user, $this->request, $this->response);
                throw new AuthenticationException($this->translator->translate('users.frontend.sign_in.signed_out'));
            }
            if ($accessToken->user_id !== $event->getUserId()) {
                throw new AuthenticationException();
            }
        }
        if (!$token) {
            throw new AuthenticationException($this->translator->translate('users.frontend.sign_in.signed_out'));
        }
    }
}
