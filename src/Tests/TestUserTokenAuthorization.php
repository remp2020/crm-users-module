<?php

namespace Crm\UsersModule\Tests;

use Crm\UsersModule\Models\Auth\AccessTokensApiAuthorizationInterface;
use Crm\UsersModule\Models\Auth\UsersApiAuthorizationInterface;
use Nette\Security\Authorizator;

class TestUserTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    private $users = [];

    private $tokens = [];

    public function __construct($token, $user = null)
    {
        $this->tokens[] = $token;

        if ($user !== null) {
            $this->users[] = $user;
        }
    }

    public function authorized($resource = Authorizator::ALL): bool
    {
        return true;
    }

    public function getErrorMessage(): ?string
    {
        return null;
    }

    public function getAuthorizedData()
    {
        return [
            'token' => reset($this->tokens),
        ];
    }

    public function getAuthorizedUsers()
    {
        return $this->users;
    }

    public function getAccessTokens()
    {
        return $this->tokens;
    }
}
