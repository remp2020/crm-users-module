<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Crm\UsersModule\Events\NewAccessTokenEvent;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\IRow;

class AccessTokensRepository extends Repository
{
    protected $tableName = 'access_tokens';

    private $emitter;

    public function __construct(
        Context $database,
        Emitter $emitter
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->emitter = $emitter;
    }

    final public function all($limit = 500)
    {
        return $this->getTable()->order('created_at DESC')->limit($limit);
    }

    final public function add(IRow $user, int $version, ?string $source = null)
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

    final public function remove($token)
    {
        $tokenRow = $this->loadToken($token);
        if (!$tokenRow) {
            return true;
        }
        $result = $this->delete($tokenRow);
        $this->emitter->emit(new RemovedAccessTokenEvent($tokenRow->user_id, $token));
        return $result;
    }

    final public function loadToken($token)
    {
        return $this->getTable()->where(['token' => $token])->fetch();
    }

    final public function allUserTokens($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC');
    }

    final public function removeAllUserTokens($userId, array $exceptTokens = [])
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

    final public function removeNotUsedTokens(DateTime $usedBefore)
    {
        $tokens = $this->getTable()->where('last_used_at < ', $usedBefore);
        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }
        return $removed;
    }

    final public function getVersionStats()
    {
        $result = [];
        $stats = $this->getTable()->select('COUNT(*) AS counts, version')->group('version');
        foreach ($stats as $stat) {
            $result[$stat->version] = $stat->counts;
        }
        return $result;
    }
}
