<?php

namespace Crm\UsersModule\Models\Email;

interface ValidatorInterface
{
    public function isValid($email): bool;
}
