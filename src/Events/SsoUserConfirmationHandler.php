<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Models\Auth\UserManager;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class SsoUserConfirmationHandler extends AbstractListener
{
    private const SSO_REGISTRATION_CHANNELS = [
        GoogleSignIn::USER_GOOGLE_REGISTRATION_CHANNEL,
        AppleSignIn::USER_APPLE_REGISTRATION_CHANNEL,
    ];

    public function __construct(
        private UserManager $userManager,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof UserRegisteredEvent) {
            throw new \Exception('Invalid type of event received, UserRegisteredEvent expected, but got: ' . get_class($event));
        }

        $user = $event->getUser();
        if (!in_array($user->registration_channel, self::SSO_REGISTRATION_CHANNELS, true)) {
            return;
        }

        $this->userManager->confirmUser($user);
    }
}
