<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddressesMetaKeyIndex extends AbstractMigration
{
    public function change(): void
    {
        $this->table('addresses_meta')
            ->addIndex('key')
            ->update();
    }
}
