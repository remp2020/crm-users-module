<?php

namespace Crm\UsersModule\Models\Auth\Sso;

class GoogleSignInConfig
{
    public function __construct(
        public ?string $clientId,
        public ?string $clientSecret,
    ) {
    }
}
