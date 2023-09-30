<?php

namespace Crm\UsersModule\Events;

use Crm\ApiModule\Repository\UserSourceAccessesRepository;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\UsersModule\Repository\UsersRepository;
use Detection\MobileDetect;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UserLastAccessHandler extends AbstractListener
{
    private $usersRepository;

    private $userSourceAccessesRepository;

    private $applicationConfig;

    public function __construct(
        UsersRepository $usersRepository,
        UserSourceAccessesRepository $userSourceAccessesRepository,
        ApplicationConfig $applicationConfig
    ) {
        $this->usersRepository = $usersRepository;
        $this->userSourceAccessesRepository = $userSourceAccessesRepository;
        $this->applicationConfig = $applicationConfig;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserLastAccessEvent)) {
            throw new \Exception("Unable to handle event, expected UserLastAccessEvent");
        }

        $source = $this->getSource($event->getSource(), $event->getUserAgent());
        $user = $event->getUser();
        if (!$user) {
            return;
        }

        $usersTokenTimeStatsEnabled = $this->applicationConfig->get('api_user_token_tracking');
        if ($usersTokenTimeStatsEnabled) {
            $this->userSourceAccessesRepository->upsert($user->id, $source, $event->getDateTime());
        }
    }

    private function getSource($source, $userAgent)
    {
        if (empty($source) || $source === UserSignInEvent::SOURCE_WEB) {
            $source = UserSignInEvent::SOURCE_WEB;
            $detector = new MobileDetect(null, $userAgent);
            if ($detector->isTablet()) {
                $source .= '_tablet';
            } elseif ($detector->isMobile()) {
                $source .= '_mobile';
            }
            return $source;
        }
        return $source;
    }
}
