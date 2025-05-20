<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserChangePasswordRequestEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private string $token,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function getToken()
    {
        return $this->token;
    }
}
