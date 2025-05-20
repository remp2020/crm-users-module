<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Models\Request;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class LoginAttemptHandler extends AbstractListener
{
    private $loginAttemptsRepository;

    private $usersRepository;

    private $emitter;

    public function __construct(
        LoginAttemptsRepository $loginAttemptsRepository,
        UsersRepository $usersRepository,
        Emitter $emitter,
    ) {
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->usersRepository = $usersRepository;
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof LoginAttemptEvent)) {
            throw new \Exception("Unable to handle, expected LoginAttemptEvent");
        }

        $dateTime = new \DateTime();

        $this->loginAttemptsRepository->insertAttempt(
            $event->getEmail(),
            $this->getUserId($event),
            $event->getSource(),
            $event->getStatus(),
            Request::getIp(),
            Request::getUserAgent(),
            $dateTime,
            $event->getMessage(),
        );

        if ($this->loginAttemptsRepository->okStatus($event->getStatus())) {
            $user = $this->usersRepository->getByEmail($event->getEmail());
            if ($user) {
                $this->emitter->emit(new UserLastAccessEvent(
                    $user,
                    $dateTime,
                    $event->getSource(),
                    Request::getUserAgent(),
                ));
            }
        }
    }

    private function getUserId(LoginAttemptEvent $event)
    {
        if (!$event->getUser()) {
            return null;
        }

        $user = $event->getUser();
        if (isset($user->id)) {
            return $user->id;
        }
        if (isset($user['id'])) {
            return $user['id'];
        }

        return null;
    }
}
