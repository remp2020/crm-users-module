<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use Crm\UsersModule\Models\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class RefreshUserDataTokenHandler extends AbstractListener
{
    private $userData;

    public function __construct(UserData $userData)
    {
        $this->userData = $userData;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof IUserGetter)) {
            throw new \Exception('cannot handle event, invalid instance received: ' . gettype($event));
        }

        $userId = $event->getUserId();
        $this->userData->refreshUserTokens($userId);
    }
}
