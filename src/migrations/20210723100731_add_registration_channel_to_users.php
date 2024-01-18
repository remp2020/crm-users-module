<?php

use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Phinx\Migration\AbstractMigration;

class AddRegistrationChannelToUsers extends AbstractMigration
{

    public function up()
    {
        $this->table('users')
            ->addColumn('registration_channel', 'string', ['default' => UsersRepository::DEFAULT_REGISTRATION_CHANNEL, 'after' => 'source'])
            ->update();

        $this->table('users')
            ->changeColumn('registration_channel', 'string', ['null' => false])
            ->save();

        $googleRegistrationChannel = GoogleSignIn::USER_GOOGLE_REGISTRATION_CHANNEL;
        $googleSource = GoogleSignIn::USER_SOURCE_GOOGLE_SSO;
        $sql = "
            UPDATE `users`
            SET `registration_channel` = '{$googleRegistrationChannel}'
            WHERE
                `source` = '{$googleSource}'";

        $this->execute($sql);

        $appleRegistrationChannel = AppleSignIn::USER_APPLE_REGISTRATION_CHANNEL;
        $appleSource = AppleSignIn::USER_SOURCE_APPLE_SSO;
        $sql = "
            UPDATE `users`
            SET `registration_channel` = '{$appleRegistrationChannel}'
            WHERE
                `source` = '{$appleSource}'";

        $this->execute($sql);
    }

    public function down()
    {
        $this->table('users')
            ->removeColumn('registration_channel')
            ->update();
    }
}
