<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\User\UserData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronizeUserDataCommand extends Command
{
    private $accessTokensRepository;

    private $userData;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UserData $userData,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->accessTokensRepository = $accessTokensRepository;
        $this->userData = $userData;
    }

    protected function configure()
    {
        $this->setName('user:synchronize_data')
            ->setDescription('Updates/synchronizes actual user token data to redis')
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Space separated user id-s');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!empty($input->getArgument('ids'))) {
            $userIds = $input->getArgument('ids');
        } else {
            $userIds = $this->accessTokensRepository->getTable()->select('user_id')->group('user_id')->fetchPairs(null, 'user_id');
        }

        $progress = new ProgressBar($output, count($userIds));
        $progress->start();

        foreach ($userIds as $userId) {
            $this->userData->refreshUserTokens($userId);
            $progress->advance();
        }

        $progress->finish();
        return 0;
    }
}
