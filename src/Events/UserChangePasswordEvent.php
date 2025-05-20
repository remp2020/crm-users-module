<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserChangePasswordEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private string $newPassword,
        private bool $notify = true,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}
