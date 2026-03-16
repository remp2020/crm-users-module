<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameModifiedAtColumntToUpdatedAtInUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->renameColumn('modified_at', 'updated_at')
            ->update();
    }
}
