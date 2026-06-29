<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveDeletedUsersFromGroups extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
            DELETE user_groups
            FROM user_groups
            INNER JOIN users ON user_groups.user_id = users.id
            WHERE users.deleted_at IS NOT NULL;
            SQL);
    }

    public function down(): void
    {
        $this->output->writeln('Down migration is not available.');
    }
}
