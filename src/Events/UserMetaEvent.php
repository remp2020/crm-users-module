<?php

declare(strict_types=1);

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserMetaEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private string $key,
        private string $value,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
