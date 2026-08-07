<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocaleIndexForUsersTable extends AbstractMigration
{
    public function change()
    {
        $this->table('users')
            ->addIndex('locale')
            ->update();
    }
}
