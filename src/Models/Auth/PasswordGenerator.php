<?php

namespace Crm\UsersModule\Models\Auth;

use Nette\Utils\Random;

class PasswordGenerator
{
    public function generatePassword($passwordLength = 8)
    {
        return Random::generate(
            length: $passwordLength,
            charlist: 'abcdefghjkmnpqrstuvzxy123456789AABCDEFGHJKMNPQRSTUVZXY',
        );
    }

    public function generatePin($pinLength = 6)
    {
        return Random::generate(
            length: $pinLength,
            charlist: '123456789',
        );
    }
}
