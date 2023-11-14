<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddressMetaKeyIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->table('addresses_meta')
            ->addIndex('key')
            ->update();
    }

    public function down(): void
    {
        $this->table('addresses_meta')
            ->removeIndex('key')
            ->update();
    }
}
