<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Models\Auth\UserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlagSuspiciousUserCommand extends Command
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('user:flag_suspicious')
            ->setDescription('Mark user flagged as suspicious')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User email',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');

        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $output->writeln("<error>User not found</error>");
            return Command::FAILURE;
        }

        $output->write("Flagging <comment>{$user->email}</comment> as suspicious: ");
        $this->userManager->suspiciousUser($user);
        $output->writeln("Done!");

        return Command::SUCCESS;
    }
}
