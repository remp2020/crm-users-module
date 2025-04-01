<?php declare(strict_types=1);

namespace Crm\UsersModule\Models\AddressChangeRequest;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum AddressChangeRequestStatusEnum: string
{
    use EnumHelper;

    case New = 'new';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
