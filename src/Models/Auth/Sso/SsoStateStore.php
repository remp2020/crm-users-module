<?php

namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\ApplicationModule\Models\Redis\RedisClientFactory;
use Crm\ApplicationModule\Models\Redis\RedisClientTrait;
use Nette\Utils\Json;

/**
 * Record of OAuth2 `state` values issued by this CRM, kept only to tell apart a callback replaying a flow we
 * already handled from a callback with a state we have never issued.
 *
 * The `gsi_state` (and equivalent) cookie remains the authoritative CSRF check - it binds the state to the
 * user agent as recommended by RFC 6749 §10.12, which a shared server-side record cannot do.
 */
class SsoStateStore
{
    use RedisClientTrait;

    public const PROVIDER_GOOGLE = 'google';

    private const KEY_PREFIX = 'sso_state';

    /**
     * Deliberately much longer than the login cookie lifetime, so that a callback arriving hours after the
     * original sign-in is still recognizable as a replay of our own flow.
     */
    private const TTL_SECONDS = 48 * 60 * 60;

    public function __construct(RedisClientFactory $redisClientFactory)
    {
        $this->redisClientFactory = $redisClientFactory;
    }

    public function issue(string $provider, string $state, ?int $userId): void
    {
        $this->store($provider, $state, [
            'issued_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'user_id' => $userId,
            'consumed_at' => null,
        ]);
    }

    public function get(string $provider, string $state): ?array
    {
        $record = $this->redis()->get($this->key($provider, $state));

        return $record === null ? null : Json::decode($record, forceArrays: true);
    }

    public function markConsumed(string $provider, string $state): void
    {
        $record = $this->get($provider, $state);
        if ($record === null) {
            return;
        }

        $record['consumed_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);
        $this->store($provider, $state, $record);
    }

    private function store(string $provider, string $state, array $record): void
    {
        $this->redis()->setex(
            $this->key($provider, $state),
            self::TTL_SECONDS,
            Json::encode($record),
        );
    }

    private function key(string $provider, string $state): string
    {
        return self::KEY_PREFIX . ":{$provider}:{$state}";
    }
}
