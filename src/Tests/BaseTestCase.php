<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesMetaRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\PasswordResetTokensRepository;
use Crm\UsersModule\Repositories\UserActionsLogRepository;
use Crm\UsersModule\Repositories\UserEmailConfirmationsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;

/**
 * Base database test case for UsersModule
 * Provides all required users repositories, so each database test case doesn't have to list them separately
 * @package Crm\UsersModule\Tests
 */
abstract class BaseTestCase extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            AddressChangeRequestsRepository::class,
            AddressesMetaRepository::class,
            AddressesRepository::class,
            AddressTypesRepository::class,
            ChangePasswordsLogsRepository::class,
            CountriesRepository::class,
            DeviceTokensRepository::class,
            GroupsRepository::class,
            LoginAttemptsRepository::class,
            PasswordResetTokensRepository::class,
            UserActionsLogRepository::class,
            UserEmailConfirmationsRepository::class,
            UserGroupsRepository::class,
            UserMetaRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
        ];
    }
}
