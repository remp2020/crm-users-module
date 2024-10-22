<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Presenters\BasePresenter;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Session;
use Nette\Security\User;

class UserUpdatedHandler extends AbstractListener
{
    public function __construct(
        private User $user,
        private Session $session
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserUpdatedEvent)) {
            throw new \Exception('Invalid type of event received, `UserUpdatedEvent` expected, but got ' . gettype($event));
        }
        $updatedUser = $event->getUser();

        // If updated user is currently logged user, flag him for reload in session
        // Actual reload happens in BasePresenter, because saving user identity in session might regenerate session ID.
        // This might break scenario when multiple-ajax requests rely on the same Session ID - e.g. ajax forms with CSRF protection.
        if ($this->user->isLoggedIn() && $updatedUser->id == $this->user->getId()) {
            $this->session->getSection('auth')->set(BasePresenter::SESSION_RELOAD_USER, true);
        }
    }
}
