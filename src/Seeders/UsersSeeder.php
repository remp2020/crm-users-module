<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Auth\Repository\AdminUserGroupsRepository;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Repository\UsersRepository;
use Symfony\Component\Console\Output\OutputInterface;

class UsersSeeder implements ISeeder
{
    public const USER_ADMIN = 'admin@crm.press';
    public const USER_CUSTOMER = 'user@crm.press';

    public function __construct(
        private UserBuilder $userBuilder,
        private UsersRepository $usersRepository,
        private AdminGroupsRepository $adminGroupsRepository,
        private AdminAccessRepository $adminAccessRepository,
        private AdminGroupsAccessRepository $adminGroupsAccessRepository,
        private AdminUserGroupsRepository $adminUserGroupsRepository,
    ) {
    }

    public function seed(OutputInterface $output)
    {
        $name = 'superadmin';

        $superGroup = $this->adminGroupsRepository->findByName($name);
        if (!$superGroup) {
            $superGroup = $this->adminGroupsRepository->add($name);
            $output->writeln("  <comment>* admin group <info>{$name}</info> created</comment>");
        } else {
            $output->writeln("  * admin group <info>{$name}</info> exists");
        }

        $this->seedAccessToHandlers($output);

        $accesses = $this->adminAccessRepository->all();
        foreach ($accesses as $access) {
            if (!$this->adminGroupsAccessRepository->exists($superGroup, $access)) {
                $this->adminGroupsAccessRepository->add($superGroup, $access);
            }
        }

        // Do not seed demo users if there are existing users in table
        if ($this->usersRepository->getTable()->limit(1)->fetch()) {
            return;
        }

        $email = self::USER_ADMIN;
        $user = $this->userBuilder->createNew()
            ->setEmail($email)
            ->setPassword('password')
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setPublicName($email)
            ->setAddTokenOption(false)
            ->setRole(UsersRepository::ROLE_ADMIN)
            ->save();

        if (!$user) {
            $output->writeln("  * user <info>$email</info> exists");
            $user = $this->usersRepository->getByEmail($email);
        } else {
            $output->writeln("  <comment>* user <info>$email</info> created</comment>");
        }

        if (!$this->adminUserGroupsRepository->exists($superGroup, $user)) {
            $this->adminUserGroupsRepository->add($superGroup, $user);
        }

        $email = self::USER_CUSTOMER;
        $user = $this->userBuilder->createNew()
            ->setEmail($email)
            ->setPassword('password')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPublicName($email)
            ->setAddTokenOption(false)
            ->save();
        if (!$user) {
            $output->writeln("  * user <info>$email</info> exists");
        } else {
            $output->writeln("  <comment>* user <info>$email</info> created</comment>");
        }
    }

    /**
     * In the past, all admin roles had access to all signals. From now signals
     * will be listed as separate access resource. We don't want to completely
     * break installed instances (and admin groups), so first time this seeder
     * is launched after signals are added by `GenerateAccessCommand`, we'll
     * seed access to all signals for all admin groups.
     */
    private function seedAccessToHandlers(OutputInterface $output)
    {
        // 1. load signals (handle prefix)
        $handleAccesses = $this->adminAccessRepository->all()
            ->where('type = ?', "handle")
            ->fetchPairs('id');

        // check if any admin group was given access to signal
        // if yes, abort seeding rights to signals
        $count = $this->adminAccessRepository->getDatabase()
            ->table('admin_groups_access')
            ->where(['admin_access_id IN (?)' => array_keys($handleAccesses)])
            ->count('*');

        if ($count > 0) {
            return;
        }

        $output->writeln("  * seeding rights to signals to <info>all admin groups</info>");

        $adminGroups = $this->adminGroupsRepository->all();

        foreach ($handleAccesses as $handleAccess) {
            foreach ($adminGroups as $adminGroup) {
                // only inserting; no signals should be assigned to admin groups; update is not needed
                $this->adminGroupsAccessRepository->add($adminGroup, $handleAccess);
            }
        }
    }
}
