<?php

namespace Crm\UsersModule\Populator;

use Crm\ApplicationModule\Populator\AbstractPopulator;
use Symfony\Component\Console\Helper\ProgressBar;

class GroupsPopulator extends AbstractPopulator
{
    /**
     * @param ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $groups = $this->database->table('groups');
        for ($i = 0; $i < $this->count; $i++) {
            $data = [
                'name' => $this->faker->word,
                'sorting' => $i * 10,
                'created_at' => $this->faker->dateTimeBetween('-2 years'),
                'updated_at' => $this->faker->dateTimeBetween('-2 years'),
            ];
            $group = $groups->insert($data);
            $this->insertUsers($group);
            $progressBar->advance();
        }
    }

    private function insertUsers($group)
    {
        $usersGroups = $this->database->table('user_groups');
        $users = $this->database->table('users');

        $randomUsers = $users->order('RAND()')->limit(random_int(0, 20));
        foreach ($randomUsers as $user) {
            $usersGroups->insert([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'created_at' => $this->faker->dateTimeBetween('-2 years'),
            ]);
        }
    }
}
