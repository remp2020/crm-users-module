<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repository\LoginAttemptsRepository;
use DeviceDetector\DeviceDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLoginAttemptsCommand extends Command
{
    private $loginAttemptsRepository;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository)
    {
        parent::__construct();
        $this->loginAttemptsRepository = $loginAttemptsRepository;
    }

    protected function configure()
    {
        $this->setName('user:fix-login-attempts')
            ->setDescription('Update login attempts browsers')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = 100;
        $updated = 0;
        $lastId = 0;
        while (true) {
            $attempts = $this->loginAttemptsRepository->all()->where(['id > ?' => $lastId])->limit($limit);
            $found = false;
            foreach ($attempts as $attempt) {
                $lastId = $attempt->id;
                $found = true;

                $deviceDetector = new DeviceDetector($attempt->user_agent);
                $deviceDetector->parse();

                $isMobile = $deviceDetector->isMobile();
                $browser = $deviceDetector->getClient('name');
                $browserVersion = $deviceDetector->getClient('version');
                $os = $deviceDetector->getOs('name');
                $device = $deviceDetector->getDeviceName();

                $this->loginAttemptsRepository->update($attempt, [
                    'browser' => $browser,
                    'browser_version' => $browserVersion,
                    'os' => $os,
                    'device' => $device,
                    'is_mobile' => $isMobile,
                ]);
                $updated++;
            }

            $output->writeln("Updated <info>{$updated}</info> attempts");

            if (!$found) {
                break;
            }
        }

        return Command::SUCCESS;
    }
}
