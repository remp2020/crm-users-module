<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use League\Event\AbstractEvent;

class UserUpdatedEvent extends AbstractEvent implements IUserGetter
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getUserId(): int
    {
        return $this->user->id;
    }
}
