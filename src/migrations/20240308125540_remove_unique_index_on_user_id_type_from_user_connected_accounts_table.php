<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUniqueIndexOnUserIdTypeFromUserConnectedAccountsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('user_connected_accounts')
            ->addIndex(['user_id'])
            ->update();

        $this->table('user_connected_accounts')
            ->removeIndex(['user_id', 'type'])
            ->update();
    }

    public function down(): void
    {
        $this->table('user_connected_accounts')
            ->addIndex(['user_id', 'type'], ['unique' => true])
            ->update();

        $this->table('user_connected_accounts')
            ->removeIndex(['user_id'])
            ->update();
    }
}
