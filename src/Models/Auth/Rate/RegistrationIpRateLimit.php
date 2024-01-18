<?php

namespace Crm\UsersModule\Models\Auth\Rate;

use Crm\UsersModule\Repository\RegistrationAttemptsRepository;
use Nette\Utils\DateTime;

class RegistrationIpRateLimit
{
    private array $rules;

    private RegistrationAttemptsRepository $registrationAttemptsRepository;

    public function __construct(RegistrationAttemptsRepository $registrationAttemptsRepository)
    {
        $this->registrationAttemptsRepository = $registrationAttemptsRepository;
    }

    public function reachLimit(string $ip): bool
    {
        foreach ($this->rules ?? [] as $rule) {
            $count = $this->registrationAttemptsRepository->getAttemptsCount($ip, $rule['startTime']);
            if ($count >= $rule['attempts']) {
                return true;
            }
        }

        return false;
    }

    public function addLimitRule(string $interval, int $maxAttemptsCount): void
    {
        $this->rules[] = [
            'startTime' => DateTime::from('-' . $interval),
            'attempts' => $maxAttemptsCount
        ];
    }
}
