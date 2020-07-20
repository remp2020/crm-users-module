<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserConfirmedEvent extends AbstractEvent
{
    private $user;

    private $isConfirmedByAdmin;

    public function __construct($user, $isConfirmedByAdmin = false)
    {
        $this->user = $user;
        $this->isConfirmedByAdmin = $isConfirmedByAdmin;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function isConfirmedByAdmin()
    {
        return $this->isConfirmedByAdmin;
    }
}
