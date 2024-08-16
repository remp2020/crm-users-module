<?php
declare(strict_types=1);

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use League\Event\AbstractEvent;

class UserResetPasswordSuccessEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(private readonly ActiveRow $user)
    {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }
}
