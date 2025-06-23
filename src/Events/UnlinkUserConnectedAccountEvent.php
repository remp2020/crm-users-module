<?php

declare(strict_types=1);

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

final class UnlinkUserConnectedAccountEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(private ActiveRow $userConnectedAccount)
    {
    }

    public function getUserConnectedAccount(): ActiveRow
    {
        return $this->userConnectedAccount;
    }

    public function getUser(): ActiveRow
    {
        return $this->userConnectedAccount->user;
    }
}
