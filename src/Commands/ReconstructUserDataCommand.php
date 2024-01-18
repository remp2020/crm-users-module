<?php

namespace Crm\UsersModule\Commands;

use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\UsersModule\Models\User\UserData;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\UnexpectedValueException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReconstructUserDataCommand extends Command
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserData $userData,
        private SegmentFactoryInterface $segmentFactory
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('user:reconstruct_user_data')
            ->setDescription('Reconstruct user data (Redis) cache based on the actual data')
            ->addOption(
                'user_ids',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'User IDs to refresh. If not provided, all users are refreshed (if segment is not provided).'
            )
            ->addOption(
                'segment',
                's',
                InputOption::VALUE_OPTIONAL,
                'Code of users segment to refresh.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Reconstructing user data');
        $output->writeln('');

        $usersQuery = $this->usersRepository->getTable()
            ->where(['active' => true])
            ->where(':access_tokens.id IS NOT NULL')
            ->order('id ASC');

        $segmentCode = $input->getOption('segment');
        if ($segmentCode) {
            try {
                $segment = $this->segmentFactory->buildSegment($segmentCode);
            } catch (UnexpectedValueException $exception) {
                $output->writeln($exception->getMessage());
                return Command::FAILURE;
            }

            $userIds = $segment->getIds();
            if (count($userIds) === 0) {
                $output->writeln('Empty segment.');
                return Command::FAILURE;
            }

            $usersQuery->where('users.id IN (?)', $userIds);
        }

        $userIds = $input->getOption('user_ids');
        if ($userIds && count($userIds)) {
            $usersQuery->where('users.id IN (?)', $userIds);
        }

        $usersQuery->group('users.id');

        $totalUsers = (clone $usersQuery)->count('*');
        $progress = new ProgressBar($output, $totalUsers);
        $progress->setFormat('debug');
        $progress->start();

        $step = 100;
        $lastId = 0;

        do {
            $processed = 0;
            $users = (clone $usersQuery)
                ->where('users.id > ?', $lastId)
                ->limit($step);

            foreach ($users as $user) {
                $this->userData->refreshUserTokens($user->id);
                $processed++;
                $lastId = $user->id;

                $progress->advance();
            }
        } while ($processed > 0);

        $progress->finish();
        $output->writeln('');
        return Command::SUCCESS;
    }
}
