<?php
declare(strict_types=1);

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Request;
use Nette\Http\Response;

class SignEventHandler extends AbstractListener
{
    public function __construct(
        private AccessToken $accessToken,
        private Request $request,
        private Response $response,
        private UsersRepository $usersRepository,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if ($event instanceof UserSignInEvent && $event->getRegenerateToken()) {
            $this->accessToken->addUserToken($event->getUser(), $this->request, $this->response, $event->getSource());
            $this->usersRepository->addSignIn($event->getUser());
        } elseif ($event instanceof UserSignOutEvent) {
            $this->accessToken->deleteActualUserToken($event->getUser(), $this->request, $this->response);
        }
    }
}
