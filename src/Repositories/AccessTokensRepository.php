<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Request;
use Crm\UsersModule\DataProviders\AccessTokenDataProviderInterface;
use Crm\UsersModule\Events\BeforeRemoveAccessTokenEvent;
use Crm\UsersModule\Events\NewAccessTokenEvent;
use Crm\UsersModule\Events\PairDeviceAccessTokensEvent;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use Crm\UsersModule\Events\UnpairDeviceAccessTokensEvent;
use Crm\UsersModule\Models\Auth\Access\TokenGenerator;
use Crm\UsersModule\Models\User\UnclaimedUser;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class AccessTokensRepository extends Repository
{
    protected $tableName = 'access_tokens';

    private $emitter;

    private $userMetaRepository;

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        Emitter $emitter,
        UserMetaRepository $userMetaRepository,
        DataProviderManager $dataProviderManager,
    ) {
        parent::__construct($database);
        $this->emitter = $emitter;
        $this->userMetaRepository = $userMetaRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    final public function all($limit = 500): Selection
    {
        return $this->getTable()->order('created_at DESC')->limit($limit);
    }

    final public function add(ActiveRow $user, int $version = 3, ?string $source = null): ActiveRow
    {
        $token = TokenGenerator::generate();

        $row = $this->insert([
            'token' => $token,
            'created_at' => new DateTime(),
            'last_used_at' => new DateTime(),
            'user_id' => $user->id,
            'ip' => Request::getIp(),
            'user_agent' => Request::getUserAgent(),
            'version' => $version,
            'source' => $source,
        ]);

        $this->emitter->emit(new NewAccessTokenEvent($user->id, $token));
        return $row;
    }

    final public function remove($token): bool
    {
        $tokenRow = $this->loadToken($token);
        if (!$tokenRow) {
            return true;
        }
        $this->emitter->emit(new BeforeRemoveAccessTokenEvent($tokenRow));
        $result = $this->delete($tokenRow);
        $this->emitter->emit(new RemovedAccessTokenEvent($tokenRow->user_id, $token, $tokenRow->source));
        return $result;
    }

    final public function loadToken($token): ?ActiveRow
    {
        return $this->getTable()->where(['token' => $token])->fetch();
    }

    final public function allUserTokens($userId): Selection
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC');
    }

    final public function allUserTokensBySource($userId, ?string $source): Selection
    {
        return $this->allUserTokens($userId)->where(['source' => $source]);
    }

    final public function allByDeviceToken(ActiveRow $deviceToken): Selection
    {
        return $this->getTable()->where('device_token_id', $deviceToken->id);
    }

    /**
     * @return ActiveRow[]
     */
    final public function findAllByDeviceToken(ActiveRow $deviceToken): array
    {
        return $this->allByDeviceToken($deviceToken)->fetchAll();
    }

    final public function pairWithDeviceToken($accessToken, $deviceToken): void
    {
        if (!$this->userMetaRepository->userMetaValueByKey($accessToken->user, UnclaimedUser::META_KEY)) {
            $this->unpairDeviceToken($deviceToken, $accessToken->user);
        }

        $this->update($accessToken, [
            'device_token_id' => $deviceToken->id,
        ]);
        $this->emitter->emit(new PairDeviceAccessTokensEvent($deviceToken, $accessToken));
    }

    final public function unpairDeviceToken($deviceToken, $user = null): void
    {
        $accessTokens = $this->allByDeviceToken($deviceToken);

        // if user provided, do not unpair device token from provided user access tokens
        if (isset($user)) {
            $accessTokens->where('user_id != ?', $user->id);
        }

        foreach ($accessTokens as $accessToken) {
            /** @var AccessTokenDataProviderInterface[] $accessTokenDataProviders */
            $accessTokenDataProviders = $this->dataProviderManager->getProviders(
                'users.dataprovider.access_tokens',
                AccessTokenDataProviderInterface::class,
            );
            foreach ($accessTokenDataProviders as $provider) {
                if (!$provider->canUnpairDeviceToken($accessToken, $deviceToken)) {
                    continue 2;
                }
            }

            $isUserUnclaimed = $this->userMetaRepository->userMetaValueByKey($accessToken->user, UnclaimedUser::META_KEY);
            if ($isUserUnclaimed) {
                continue;
            }

            $this->emitter->emit(new UnpairDeviceAccessTokensEvent($deviceToken, $accessToken));
            $this->update($accessToken, ['device_token_id' => null]);
        }
    }

    final public function removeAllUserTokens($userId, array $exceptTokens = []): int
    {
        $tokens = $this->getTable()->where(['user_id' => $userId]);

        if ($exceptTokens) {
            $tokens->where('token NOT IN (?)', $exceptTokens);
        }

        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }

        return $removed;
    }

    final public function removeNotUsedTokens(DateTime $usedBefore): int
    {
        $tokens = $this->getTable()->where('last_used_at < ', $usedBefore);
        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }
        return $removed;
    }

    /**
     * @return array<int, int>
     */
    final public function getVersionStats(): array
    {
        $result = [];
        $stats = $this->getTable()->select('COUNT(*) AS counts, version')->group('version');
        foreach ($stats as $stat) {
            $result[$stat->version] = $stat->counts;
        }
        return $result;
    }

    final public function existsForUserDeviceToken(ActiveRow $user, ActiveRow $deviceToken) : bool
    {
        return $this->getTable()->where([
            'device_token_id' => $deviceToken->id,
            'user_id' => $user->id,
        ])->count('*') > 0;
    }
}
