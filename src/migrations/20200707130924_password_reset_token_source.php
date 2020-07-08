<?php

use Phinx\Migration\AbstractMigration;

class PasswordResetTokenSource extends AbstractMigration
{
    public function change()
    {
        $this->table('password_reset_tokens')
            ->addColumn('source', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
