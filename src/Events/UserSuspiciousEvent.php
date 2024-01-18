<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use League\Event\AbstractEvent;

class UserSuspiciousEvent extends AbstractEvent implements IUserGetter
{
    private $user;

    private $newPassword;

    public function __construct($user, string $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getUserId(): int
    {
        return $this->user->id;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}
