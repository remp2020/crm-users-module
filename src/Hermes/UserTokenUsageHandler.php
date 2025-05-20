<?php

namespace Crm\UsersModule\Hermes;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\UsersModule\Events\UserLastAccessEvent;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class UserTokenUsageHandler implements HandlerInterface
{
    private $accessTokensRepository;

    private $emitter;

    private $applicationConfig;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        Emitter $emitter,
        ApplicationConfig $applicationConfig,
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
        $this->applicationConfig = $applicationConfig;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['token'])) {
            throw new UserTokenUsageException("missing required payload param: token");
        }
        if (!isset($payload['user_agent'])) {
            throw new UserTokenUsageException("missing required payload param: user_agent");
        }
        if (!isset($payload['source'])) {
            throw new UserTokenUsageException("missing required payload param: source");
        }

        $token = $this->accessTokensRepository->loadToken($payload['token']);

        if (!$token) {
            // Not throwing exception since it generates just too many log entries
            return true;
        }

        $accessDate = new DateTime();
        $usersTokenTimeStatsEnabled = $this->applicationConfig->get('api_user_token_tracking');
        if ($usersTokenTimeStatsEnabled) {
            $this->accessTokensRepository->update($token, ['last_used_at' => $accessDate]);
        }

        $this->emitter->emit(new UserLastAccessEvent(
            $token->user,
            $accessDate,
            $payload['source'],
            $payload['user_agent'],
        ));

        return true;
    }
}
