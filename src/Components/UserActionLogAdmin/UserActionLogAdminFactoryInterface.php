<?php

namespace Crm\UsersModule\Components\UserActionLogAdmin;

interface UserActionLogAdminFactoryInterface
{
    public function create(): UserActionLogAdmin;
}
