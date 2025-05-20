<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Models\Email\EmailValidator;
use Crm\UsersModule\Repositories\UsersRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckEmailsCommand extends Command
{
    private UsersRepository $userRepository;

    private EmailValidator $emailValidator;

    public function __construct(
        UsersRepository $userRepository,
        EmailValidator $emailValidator,
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->emailValidator = $emailValidator;
    }

    protected function configure()
    {
        $this->setName('user:check-emails')
            ->setDescription('Validate emails')
            ->addArgument(
                'email',
                InputArgument::OPTIONAL,
                'User email',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('email')) {
            $this->checkEmail($output, $input->getArgument('email'));
            return Command::SUCCESS;
        }

        $step = 1000;
        $offset = 0;
        while (true) {
            $users = $this->userRepository->all()->where(['active' => true])->limit($step, $offset);
            foreach ($users as $user) {
                $this->checkEmail($output, $user->email);
                $offset++;
            }

            $count = count($users);
            $offset += $count;
            if ($count < $step) {
                break;
            }
        }

        return Command::SUCCESS;
    }

    private function checkEmail(OutputInterface $output, string $email): void
    {
        $output->write("Checking email: <comment>{$email}</comment> ... ");
        $result = $this->emailValidator->isValid($email);
        if ($result) {
            $output->writeln("<info>VALID</info>");
        } else {
            $output->writeln("<error>INVALID</error> - ({$this->getErrorValidator()})");
        }
    }

    private function getErrorValidator(): string
    {
        if ($this->emailValidator->lastValidator()) {
            return get_class($this->emailValidator->lastValidator());
        }
        return "";
    }
}
