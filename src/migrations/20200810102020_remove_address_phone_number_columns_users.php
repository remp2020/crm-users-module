<?php

use Phinx\Migration\AbstractMigration;

class RemoveAddressPhoneNumberColumnsUsers extends AbstractMigration
{
    public function up()
    {
        $now = (new DateTime())->format('\'Y-m-d H:i:s\'');
        $this->execute("INSERT INTO `user_meta` (`user_id`, `key`, `value`, `created_at`, `updated_at`)
                            SELECT
                               `id`,
                               'deprecated_address',
                               `address`,
                               $now,
                               $now
                            FROM `users`
                            WHERE `address` IS NOT NULL AND TRIM(`address`) != '';
                            ");

        $this->execute("INSERT INTO `user_meta` (`user_id`, `key`, `value`, `created_at`, `updated_at`)
                            SELECT
                               `id`,
                               'deprecated_phone_number',
                               `phone_number`,
                               $now,
                               $now
                            FROM `users`
                            WHERE `phone_number` IS NOT NULL AND TRIM(`phone_number`) != '';
                            ");

        $this->table('users')
            ->removeColumn('address')
            ->removeColumn('phone_number')
            ->update();
    }

    public function down()
    {
        $now = (new DateTime())->format('\'Y-m-d H:i:s\'');
        $this->table('users')
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('phone_number', 'string', ['limit' => 255, 'null' => true])
            ->update();

        $this->execute("UPDATE `users` `u`
                            JOIN
                                `user_meta` `um` ON `u`.`id` = `um`.`user_id` 
                            SET
                                `u`.`address` = `um`.`value`,   
                                `u`.`modified_at` = $now
                            WHERE
                                `um`.`key` = 'deprecated_address'
                            ");

        $this->execute("UPDATE `users` `u`
                            JOIN
                                `user_meta` `um` ON `u`.`id` = `um`.`user_id` 
                            SET
                                `u`.`phone_number` = `um`.`value`,   
                                `u`.`modified_at` = $now
                            WHERE
                                `um`.`key` = 'deprecated_phone_number'
                            ");

        $builder = $this->getDeleteBuilder();
        $builder->delete('user_meta')
            ->whereInList('user_meta.key', ['deprecated_address', 'deprecated_phone_number'])
            ->execute();
    }
}
