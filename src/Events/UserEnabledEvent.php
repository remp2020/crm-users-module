<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserEnabledEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(private ActiveRow $user)
    {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }
}
