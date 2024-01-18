<?php

namespace Crm\UsersModule\Events;

use Crm\AdminModule\Helpers\SecuredAdminAccess;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use League\Event\AbstractListener;
use League\Event\EventInterface;

/**
 * SecureAccessSignInEventHandler is responsible for enforcing sign-in rules when accessing secured parts of the system.
 * For sign-ins which match the rule, this handler flags the access "secure" by calling:
 *
 *      $this->securedAdminAccess->setSecure(true);
 *
 * You can use your own handler if necessary; just make sure to flag the "secure" access when rules are matched.
 */
class SecureAccessSignInEventHandler extends AbstractListener
{
    public function __construct(
        private SecuredAdminAccess $securedAdminAccess
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof UserSignInEvent) {
            throw new \Exception("Invalid type of event received, UserSignInEvent expected, got: " . get_class($event));
        }

        $source = $event->getSource();
        if ($source === GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO) {
            $this->securedAdminAccess->setSecure(true);
            return;
        }

        $this->securedAdminAccess->setSecure(false);
    }
}
