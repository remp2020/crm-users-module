<?php

namespace Crm\UsersModule\Events;

use Crm\ApiModule\Repositories\UserSourceAccessesRepository;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\UsersModule\Models\DeviceDetector;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UserLastAccessHandler extends AbstractListener
{
    public function __construct(
        private readonly UserSourceAccessesRepository $userSourceAccessesRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly DeviceDetector $deviceDetector,
    ) {
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

            $this->deviceDetector->setUserAgent($userAgent);
            $this->deviceDetector->parse();

            if ($this->deviceDetector->isTablet()) {
                $source .= '_tablet';
            } elseif ($this->deviceDetector->isMobile()) {
                $source .= '_mobile';
            }
            return $source;
        }
        return $source;
    }
}
