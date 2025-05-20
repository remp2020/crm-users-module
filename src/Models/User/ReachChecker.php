<?php

namespace Crm\UsersModule\Models\User;

use Crm\UsersModule\Repositories\UserMetaRepository;
use Nette\Database\Table\ActiveRow;

class ReachChecker
{
    public const USER_META_UNREACHABLE = 'unreachable';

    private UserMetaRepository $userMetaRepository;

    private UnclaimedUser $unclaimedUser;

    public function __construct(
        UnclaimedUser $unclaimedUser,
        UserMetaRepository $userMetaRepository,
    ) {
        $this->userMetaRepository = $userMetaRepository;
        $this->unclaimedUser = $unclaimedUser;
    }

    public function isReachable(ActiveRow $user)
    {
        if ($user->deleted_at) {
            return false;
        }
        if (!$user->active) {
            return false;
        }
        if ($this->unclaimedUser->isUnclaimedUser($user)) {
            return false;
        }
        if ($this->userMetaRepository->userMetaValueByKey($user, self::USER_META_UNREACHABLE) === '1') {
            return false;
        }
        return true;
    }
}
