<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

/**
 * UserRegisteredEvent is emitted when new user is registered during standard registration flow.
 * Unlike NewUserEvent there is option in UserBuilder to disable event emitting.
 *
 * States the user can go through: new - REGISTERED (this) - disabled - deleted.
 */
class UserRegisteredEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private string $originalPassword,
        private bool $sendEmail = false,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function sendEmail(): bool
    {
        return $this->sendEmail;
    }

    public function getOriginalPassword(): string
    {
        return $this->originalPassword;
    }
}
