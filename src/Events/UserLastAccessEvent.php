<?php

namespace Crm\UsersModule\Events;

use DateTime;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class UserLastAccessEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private DateTime $dateTime,
        private $source,
        private $userAgent,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }
}
