<?php

namespace Crm\UsersModule\Models\Auth\Sso;

class AlreadyLinkedAccountSsoException extends \Exception
{
    protected $externalId;

    protected $email;

    public function __construct($externalId, $email)
    {
        parent::__construct("Account {$email}-{$externalId}");
        $this->externalId = $externalId;
        $this->email = $email;
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
