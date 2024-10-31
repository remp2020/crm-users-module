<?php

namespace Crm\UsersModule\Models\Auth\Access;

class TokenGenerator
{
    /**
     * Function to generate security tokens (for sessions, autologin tokens, etc.)
     * Minimal recommended length of security tokens is 128 bit (16 bytes)
     * See OWASP guide for details:
     * https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/Session_Management_Cheat_Sheet.md
     *
     * @param int $bytes
     *
     * @return string of length bytes * 2 (hexadecimal string)
     * @throws \Exception - if no sufficient entropy is available
     */
    public static function generate(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
