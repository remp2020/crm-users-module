<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class SoftDeleteGroups extends AbstractMigration
{
    public function up(): void
    {
        $this->table('groups')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'updated_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('groups')
            ->removeColumn('deleted_at')
            ->update();
    }
}
