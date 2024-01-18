<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApplicationModule\Router\RedirectValidator;

/*
 * @deprecated (2022/11) Please use RedirectValidator directly, this class will be removed in the future.
 */
class SignInRedirectValidator
{
    public function __construct(private RedirectValidator $redirectValidator)
    {
    }

    public function addAllowedDomains(string...$domains): void
    {
        $this->redirectValidator->addAllowedDomains(...$domains);
    }

    public function isAllowed(string $url): bool
    {
        return $this->redirectValidator->isAllowed($url);
    }
}
