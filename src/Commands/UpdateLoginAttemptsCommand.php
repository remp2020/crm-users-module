<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Models\DeviceDetector;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLoginAttemptsCommand extends Command
{
    public function __construct(
        private LoginAttemptsRepository $loginAttemptsRepository,
        private DeviceDetector $deviceDetector,
    ) {
        parent::__construct();
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

                $this->deviceDetector->setUserAgent($attempt->user_agent);
                $this->deviceDetector->parse();

                $isMobile = $this->deviceDetector->isMobile();
                $browser = $this->deviceDetector->getClient('name');
                $browserVersion = $this->deviceDetector->getClient('version');
                $os = $this->deviceDetector->getOs('name');
                $device = $this->deviceDetector->getDeviceName();

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
