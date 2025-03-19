<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameStreetPartOfAddressesToStreet extends AbstractMigration
{
    public function change(): void
    {
        $this->table('addresses')
            ->renameColumn('address', 'street')
            ->update();

        $this->table('address_change_requests')
            ->renameColumn('address', 'street')
            ->renameColumn('old_address', 'old_street')
            ->update();
    }
}
