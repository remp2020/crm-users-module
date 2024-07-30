<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewAddressChangeRequestEvent extends AbstractEvent
{
    public function __construct(private readonly ActiveRow $changeRequest)
    {
    }

    public function getChangeRequest(): ActiveRow
    {
        return $this->changeRequest;
    }
}
