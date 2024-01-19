<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveLastSignInAtAndLastSignInIpFromUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->removeColumn('last_sign_in_at')
            ->removeColumn('last_sign_in_ip')
            ->update();
    }

    public function down(): void
    {
        $this->table('users')
            ->addColumn('last_sign_in_at', 'datetime', array('null' => true))
            ->addColumn('last_sign_in_ip','string', array('null' => true))
            ->update();
    }
}
