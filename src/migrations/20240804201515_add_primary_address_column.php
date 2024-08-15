<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPrimaryAddressColumn extends AbstractMigration
{
    public function change()
    {
        $this->table('addresses')
            ->addColumn('is_default', 'boolean', ['default' => false, 'after' => 'type'])
            ->update();
    }
}
