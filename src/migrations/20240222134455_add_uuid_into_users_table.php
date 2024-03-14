<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUuidIntoUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('uuid', 'string', ['after' => 'ext_id'])
            ->addIndex('uuid', ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('uuid')
            ->update();
    }
}
