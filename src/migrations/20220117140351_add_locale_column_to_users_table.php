<?php

use Contributte\Translation\Translator;
use Phinx\Migration\AbstractMigration;

class AddLocaleColumnToUsersTable extends AbstractMigration
{ 
    public function up()
    {        
        $app = $GLOBALS['application'] ?? null;
        if (!$app) {
            throw new \Exception("Unable to load application from \$GLOBALS['application'] variable, cannot load default locale.");
        }
        
        $translator = $app->getContainer()->getByType(Translator::class);
        $defaultLocale = $translator->getDefaultLocale() ?? 'en_US';
        
        $this->table('users')
            ->addColumn('locale', 'string', [ 'null' => true, 'after' => 'note' ])
            ->update();

        $this->query("UPDATE `users` SET locale = '${defaultLocale}'");

        $this->table('users')
            ->changeColumn('locale', 'string', [ 'null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('users')->removeColumn('locale')->update();
    }
}
