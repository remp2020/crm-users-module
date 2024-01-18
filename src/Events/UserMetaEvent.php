<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use League\Event\AbstractEvent;

class UserMetaEvent extends AbstractEvent implements IUserGetter
{
    private $userId;

    private $key;

    private $value;

    public function __construct(int $userId, $key, $value)
    {
        $this->userId = $userId;
        $this->key = $key;
        $this->value = $value;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getValue()
    {
        return $this->value;
    }
}
