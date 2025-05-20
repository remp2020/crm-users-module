<?php

namespace Crm\UsersModule\Models;

use Crm\ApplicationModule\Helpers\ForwardsCalls;
use Crm\ApplicationModule\Models\Redis\RedisClientFactory;
use Crm\ApplicationModule\Models\Redis\RedisClientTrait;
use DeviceDetector\Cache\CacheInterface;
use DeviceDetector\DeviceDetector as MatomoDeviceDetector;

/**
 * Proxy class forwarding all method calls to Matomo DeviceDetector,
 * initiated with Redis cache.
 *
 * @method setUserAgent($getUserAgent)
 * @method parse()
 * @method isBot()
 * @method isMobile()
 * @method getClient(string $string)
 * @method getOs(string $string)
 * @method getDeviceName()
 * @method getModel()
 * @method isTablet()
 */
class DeviceDetector
{
    use ForwardsCalls;

    private MatomoDeviceDetector $deviceDetector;

    public function __construct(
        RedisClientFactory $redisClientFactory,
    ) {
        $dd = new MatomoDeviceDetector();
        $dd->setCache(
            new class($redisClientFactory) implements CacheInterface {
                use RedisClientTrait;

                private const KEY = "device_detector";

                public function __construct($redisClientFactory)
                {
                    $this->redisClientFactory = $redisClientFactory;
                }

                public function fetch(string $id)
                {
                    $d = $this->redis()->hget(self::KEY, $id);
                    return $d === null ? null : unserialize($d);
                }

                public function contains(string $id): bool
                {
                    return (bool) $this->redis()->hexists(self::KEY, $id);
                }

                public function save(string $id, $data, int $lifeTime = 0): bool
                {
                    // Intentionally ignoring $lifeTime, since REDIS hash single entries does not support TTL.
                    // It should not be a problem though, since Matomo doesn't send $lifetime in current implementation.

                    $count = $this->redis()->hset(self::KEY, $id, serialize($data));
                    return $count > 0;
                }

                public function delete(string $id): bool
                {
                    $this->redis()->hdel(self::KEY, $id);
                    return true;
                }

                public function flushAll(): bool
                {
                    $this->redis->del(self::KEY);
                    return true;
                }
            },
        );
        $this->deviceDetector = $dd;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->forwardCallTo($this->deviceDetector, $name, $arguments);
    }
}
