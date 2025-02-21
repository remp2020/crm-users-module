<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeleteUserStatsOfAnonymizedUsers extends AbstractMigration
{
    public function up(): void
    {
        // disable foreign key checks for session while deleting user stats
        // (speeds up deletion && no need to check foreign keys in this case)
        $isForeignKeyCheck = $this->fetchRow('SELECT @@foreign_key_checks;');
        if ($isForeignKeyCheck[0]) {
            $this->query('SET foreign_key_checks = 0;');
        }

        $count = $this->fetchRow(<<<SQL
            SELECT COUNT(`id`) AS `cnt`
            FROM `users`
            WHERE `users`.`deleted_at` IS NOT NULL
        SQL);

        $limit = 5000;
        $offset = 0;

        while ($offset < $count['cnt']) {
            $userIds = $this->query(<<<SQL
                SELECT `id`
                FROM `users`
                WHERE
                    `users`.`deleted_at` IS NOT NULL
                ORDER BY `id`
                LIMIT {$limit} OFFSET {$offset}
            SQL);
            if ($userIds->rowCount() <= 0) {
                break;
            }

            $userIds = array_column($userIds->fetchAll(), 'id');
            // Phinx doesn't support array as parameter of query / execute ...
            $userIds = implode(",", $userIds);

            $this->execute(<<<SQL
                DELETE `user_stats`
                FROM `user_stats`
                WHERE `user_id` IN ({$userIds})
            SQL);

            $offset += $limit;
        }

        if ($isForeignKeyCheck[0]) {
            $this->query('SET foreign_key_checks = 1;');
        }
    }

    public function down(): void
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
