<?php

namespace Crm\UsersModule\Models\Auth\Rate;

use Nette\Security\AuthenticationException;

class RateLimitException extends AuthenticationException
{

}
