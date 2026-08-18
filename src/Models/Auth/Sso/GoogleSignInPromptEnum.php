<?php declare(strict_types=1);

namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\ApplicationModule\Helpers\EnumHelper;

/**
 * `prompt` parameter of Google's authorization request.
 *
 * See https://developers.google.com/identity/openid-connect/openid-connect#prompt.
 */
enum GoogleSignInPromptEnum: string
{
    use EnumHelper;

    case None = 'none';
    case Consent = 'consent';
    case SelectAccount = 'select_account';
}
