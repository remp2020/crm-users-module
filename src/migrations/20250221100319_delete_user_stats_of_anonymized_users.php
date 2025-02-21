<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeleteUserStatsOfAnonymizedUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
            DELETE `user_stats`
            FROM `user_stats`
            INNER JOIN `users`
               ON `user_stats`.`user_id` = `users`.`id`
            WHERE
                `users`.`deleted_at` IS NOT NULL
        SQL
        );
    }

    public function down(): void
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
