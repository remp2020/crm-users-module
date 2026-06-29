<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddUniqueConstraintToUserGroups extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            DELETE ug FROM user_groups ug
            INNER JOIN user_groups ug2
                ON ug2.user_id = ug.user_id
               AND ug2.group_id = ug.group_id
               AND ug2.id > ug.id
        SQL);

        $this->table('user_groups')
            ->addIndex(['user_id', 'group_id'], ['unique' => true, 'name' => 'user_id_group_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('user_groups')
            ->removeIndexByName('user_id_group_id')
            ->update();
    }
}
