<?php

namespace Crm\UsersModule\Populators;

use Crm\ApplicationModule\Populators\AbstractPopulator;
use Symfony\Component\Console\Helper\ProgressBar;

class AutologinPopulator extends AbstractPopulator
{
    /**
     * @param ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $autologin = $this->database->table('autologin_tokens');
        for ($i = 0; $i < $this->count; $i++) {
            $maxUsed = $this->faker->randomDigitNotNull;
            $user = $this->getRecord('users');
            $autologin->insert([
                'token' => $this->faker->md5,
                'user_id' => $user->id,
                'email' => $user->email,
                'created_at' => $this->faker->dateTimeBetween('-2 years'),
                'valid_from' => $this->faker->dateTimeBetween('-2 years'),
                'valid_to' => $this->faker->dateTimeBetween('-2 years'),
                'used_count' => random_int(1, $maxUsed),
                'max_count' => $maxUsed,
            ]);
            $progressBar->advance();
        }
    }
}
