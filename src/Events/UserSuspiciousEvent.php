<?php

declare(strict_types=1);

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserSuspiciousEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private string $newPassword,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}
