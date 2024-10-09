<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAddressChangeRequestsForeignKeyCountries extends AbstractMigration
{
    public function up(): void
    {
        $this->table('address_change_requests')
            ->addForeignKey('country_id', 'countries')
            ->save();
    }

    public function down()
    {
        $this->table('address_change_requests')
            ->dropForeignKey('country_id')
            ->save();
    }
}
