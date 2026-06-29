<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class SoftDeleteUserGroups extends AbstractMigration
{
    public function up(): void
    {
        $this->table('user_groups')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'created_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('user_groups')
            ->removeColumn('deleted_at')
            ->update();
    }
}
