<?php

use Phinx\Migration\AbstractMigration;

class LoginAttemptIndices extends AbstractMigration
{
    public function up()
    {
        $this->table('login_attempts')
            ->removeIndex('os')
            ->removeIndex('device')
            ->addIndex('source')
            ->update();
    }

    public function down()
    {
        $this->table('login_attempts')
            ->removeIndex('source')
            ->addIndex('os')
            ->addIndex('device')
            ->update();
    }
}
