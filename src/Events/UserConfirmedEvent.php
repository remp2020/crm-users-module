<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserConfirmedEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private bool $isConfirmedByAdmin = false,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function isConfirmedByAdmin(): bool
    {
        return $this->isConfirmedByAdmin;
    }
}
