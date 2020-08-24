<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Auth\Access\AccessToken;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Request;
use Nette\Http\Response;

class SignEventHandler extends AbstractListener
{
    /** @var AccessToken */
    private $accessToken;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    public function __construct(AccessToken $accessToken, Request $request, Response $response)
    {
        $this->accessToken = $accessToken;
        $this->request = $request;
        $this->response = $response;
    }

    public function handle(EventInterface $event)
    {
        if ($event instanceof UserSignInEvent && $event->getRegenerateToken()) {
            $this->accessToken->addUserToken($event->getUser(), $this->request, $this->response, $event->getSource());
        } elseif ($event instanceof UserSignOutEvent) {
            $this->accessToken->deleteActualUserToken($event->getUser(), $this->request, $this->response);
        }
    }
}
