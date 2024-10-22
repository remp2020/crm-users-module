<?php

declare(strict_types=1);

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class RefreshUserDataTokenHandler extends AbstractListener
{
    public function __construct(
        private UserData $userData,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserEventInterface)) {
            throw new \Exception('Invalid type of event received, `UserEventInterface` expected, but got ' . gettype($event));
        }

        $this->userData->refreshUserTokens($event->getUser()->id);
    }
}
