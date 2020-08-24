<?php

use Phinx\Migration\AbstractMigration;

class AccessTokenSource extends AbstractMigration
{
    public function change()
    {
        $this->table('access_tokens')
            ->addColumn('source', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
