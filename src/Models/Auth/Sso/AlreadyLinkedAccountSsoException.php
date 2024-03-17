<?php

namespace Crm\UsersModule\Models\Auth\Sso;

class AlreadyLinkedAccountSsoException extends \Exception
{
    public function __construct(protected $externalId, protected $email)
    {
        parent::__construct("Account {$email}-{$externalId}");
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getExternalId()
    {
        return $this->externalId;
    }
}
