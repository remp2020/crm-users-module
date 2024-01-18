<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repositories\UsersRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DisableUserCommand extends Command
{
    private $usersRepository;

    public function __construct(
        UsersRepository $usersRepository
    ) {
        parent::__construct();
        $this->usersRepository = $usersRepository;
    }

    protected function configure()
    {
        $this->setName('user:disable')
            ->setDescription('Disable user')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User email'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $user =  $this->usersRepository->getByEmail($email);
        if (!$user) {
            $output->writeln("<error>User not found</error>");
            return Command::FAILURE;
        }

        if (!$user->active) {
            $output->writeln("<error>Inactive user</error>");
            return Command::SUCCESS;
        }

        $this->usersRepository->toggleActivation($user);
        $output->writeln("Done");

        return Command::SUCCESS;
    }
}
