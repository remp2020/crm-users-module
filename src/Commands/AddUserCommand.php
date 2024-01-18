<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\UsersRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddUserCommand extends Command
{
    /** @var UserBuilder */
    private $userBuilder;

    public function __construct(
        UserBuilder $userBuilder
    ) {
        parent::__construct();
        $this->userBuilder = $userBuilder;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('user:add')
            ->setDescription('Create new user')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User email'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'User password'
            )
            ->addArgument(
                'first_name',
                InputArgument::OPTIONAL,
                'User firstname'
            )
            ->addArgument(
                'last_name',
                InputArgument::OPTIONAL,
                'User lastname'
            )
            ->addArgument(
                'public_name',
                InputArgument::OPTIONAL,
                'Public name (if not set; email will be used)'
            )
            ->addOption(
                'admin',
                null,
                InputOption::VALUE_NONE,
                'If set, user will be admin'
            )
            ->addOption(
                'inactive',
                null,
                InputOption::VALUE_NONE,
                'If set, user will be inactive'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** ADD USER *****</info>');
        $output->writeln('');

        $role = UsersRepository::ROLE_USER;
        if ($input->getOption('admin')) {
            $role = UsersRepository::ROLE_ADMIN;
        }

        $active = true;
        if ($input->getOption('inactive')) {
            $active = false;
        }

        $userBuilder = $this->userBuilder->createNew()
            ->setEmail($input->getArgument('email'))
            ->setPassword($input->getArgument('password'))
            ->setRole($role)
            ->setActive($active);

        if ($input->getArgument('last_name')) {
            $userBuilder->setLastName($input->getArgument('last_name'));
        }
        if ($input->getArgument('first_name')) {
            $userBuilder->setFirstName($input->getArgument('first_name'));
        }
        if ($input->getArgument('public_name')) {
            $userBuilder->setPublicName($input->getArgument('public_name'));
        } else {
            $userBuilder->setPublicName($input->getArgument('email'));
        }

        $user = $userBuilder->save();

        if (!$user) {
            $errors = $this->userBuilder->getErrors();
            $output->writeln('Error ' . implode("\n", $errors));
        } else {
            $output->writeln("User <info>{$user->email}</info> [<comment>{$user->id}</comment>] was added.");
        }

        return Command::SUCCESS;
    }
}
