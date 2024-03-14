<?php
declare(strict_types=1);

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repositories\UsersRepository;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateUuidForUsersCommand extends Command
{
    public function __construct(
        private UsersRepository $usersRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('user:generate_uuid_for_users')
            ->setDescription('Generate UUID for existing non-deleted users.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lastId = 0;
        do {
            $output->writeln("Generate UUID for users starting with ID: {$lastId}");
            $users = $this->usersRepository->getTable()
                ->where('uuid', null)
                ->where('deleted_at', null)
                ->where('id > ?', $lastId)
                ->order('id')
                ->limit(1000);

            foreach ($users as $user) {
                $lastId = $user->id;
                $this->usersRepository->update($user, ['uuid' => Uuid::uuid4()]);
            }
        } while ($users->count('*') > 0);

        return Command::SUCCESS;
    }
}
