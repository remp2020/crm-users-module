<?php

namespace Crm\UsersModule\Populators;

use Crm\ApplicationModule\Populators\AbstractPopulator;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Security\Passwords;
use Symfony\Component\Console\Helper\ProgressBar;

class UsersPopulator extends AbstractPopulator
{
    /** @var Passwords */
    private $passwords;

    public function __construct($name, $count, Passwords $passwords)
    {
        $this->passwords = $passwords;
        parent::__construct($name, $count);
    }
    /**
     * @param ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $users = $this->database->table('users');
        $loginAttempts = $this->database->table('login_attempts');
        $changePasswordsLog = $this->database->table('change_passwords_logs');
        for ($i = 0; $i < $this->count; $i++) {
            $email = $this->faker->email;
            $data = [
                'email' => $email,
                'public_name' => $email,
                'locale' => $this->faker->locale,
                'registration_channel' => $this->faker->randomElement(['crm', 'google', 'apple']),
                'source' => $this->faker->randomElement(['follow', 'friend_lock', 'funnel']),
                'password' => $this->passwords->hash($this->faker->userName),
                'first_name' => $this->faker->firstName,
                'last_name' => $this->faker->lastName,
                'ext_id' => $this->faker->boolean(5) ? null : $this->faker->numberBetween(100, 10000),
                'role' => UsersRepository::ROLE_USER,
                'active' => $this->faker->boolean(5) ? 0 : 1,
                'current_sign_in_at' => $this->faker->dateTimeBetween('-4 months'),
                'created_at' => $this->faker->dateTimeBetween('-4 months'),
                'modified_at' => $this->faker->dateTimeBetween('-4 months'),
                'current_sign_in_ip' => $this->faker->boolean(5) ? null : $this->faker->ipv4,
                'invoice' => random_int(1, 4) == 3 ? true : false,
                'note' => random_int(1, 4) == 3 ? $this->faker->sentence : null,
            ];
            if ($this->faker->boolean(10)) {
                $data['is_institution'] = true;
                $data['institution_name'] = $this->faker->company;
            }

            $user = $users->insert($data);

            $loginAttemptsCount = $this->faker->numberBetween(0, 30);
            for ($j = 0; $j < $loginAttemptsCount; $j++) {
                $this->insertLoginAttempt($loginAttempts, $user);
            }

            $changePasswordCount = $this->faker->numberBetween(0, 5);
            for ($j = 0; $j < $changePasswordCount; $j++) {
                $this->insertChangePasswordLog($changePasswordsLog, $user);
            }

            $progressBar->advance();
        }
    }

    private function insertLoginAttempt($table, $user)
    {
        return $table->insert([
            'user_id' => $user->id,
            'email' => $this->faker->boolean(5) ? $this->faker->email : $user->email,
            'created_at' => $this->faker->dateTimeBetween('-3 months'),
            'status' => $this->randomAttemptStatus(),
            'ip' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'source' => $this->faker->randomElement(['web', 'api']),
        ]);
    }

    private function insertChangePasswordLog($table, $user)
    {
        return $table->insert([
            'user_id' => $user->id,
            'created_at' => new \DateTime(),
            'type' => random_int(1, 2) == 1 ? ChangePasswordsLogsRepository::TYPE_CHANGE : ChangePasswordsLogsRepository::TYPE_RESET,
            'from_password' => $this->faker->md5,
            'to_password' => $this->faker->md5,
        ]);
    }

    private function randomAttemptStatus()
    {
        $statuses = [
            LoginAttemptsRepository::STATUS_OK,
            LoginAttemptsRepository::STATUS_NOT_FOUND_EMAIL,
            LoginAttemptsRepository::STATUS_WRONG_PASS,
        ];
        return $statuses[random_int(0, count($statuses) - 1)];
    }
}
