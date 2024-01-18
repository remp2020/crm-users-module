<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class RemovedAccessTokenHandler extends AbstractListener
{
    private $userData;

    public function __construct(UserData $userData)
    {
        $this->userData = $userData;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof RemovedAccessTokenEvent)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $token = $event->getAccessToken();
        $this->userData->removeUserToken($token);
    }
}
