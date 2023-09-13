<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Events\UserMetaEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class UserMetaRepository extends Repository
{
    private $emitter;

    protected $tableName = 'user_meta';

    public function __construct(Explorer $database, Emitter $emitter, Storage $cacheStorage = null)
    {
        parent::__construct($database, $cacheStorage);
        $this->emitter = $emitter;
    }

    final public function exists(ActiveRow $user, $key)
    {
        return $this->getTable()->where(['user_id' => $user->id, 'key' => $key])->count('*') > 0;
    }

    final public function add(ActiveRow $user, $key, $value, ?DateTime $createdAt = null, $isPublic = false)
    {
        if ($this->exists($user, $key)) {
            $result = $this->getTable()->where(['user_id' => $user, 'key' => $key])
                ->update(['value' => $value, 'updated_at' => new DateTime(), 'is_public' => $isPublic]);
            if ($result) {
                $this->emitter->emit(new UserMetaEvent($user->id, $key, $value));
            }

            return $this->getTable()->where(['user_id' => $user, 'key' => $key])->fetch();
        }

        $result = $this->insert([
            'user_id' => $user->id,
            'key' => $key,
            'value' => $value,
            'is_public' => $isPublic,
            'created_at' => $createdAt ?? new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        if ($result) {
            $this->emitter->emit(new UserMetaEvent($user->id, $key, $value));
        }
        return $result;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function setMeta(ActiveRow $user, array $metas, $isPublic = false)
    {
        foreach ($metas as $key => $value) {
            $this->add($user, $key, $value, null, $isPublic);
        }
    }

    final public function removeMeta($userId, $key, $value = null)
    {
        $selection = $this->getTable()->where(['user_id' => $userId, 'key' => $key]);
        if ($value !== null) {
            $selection->where('value = ?', $value);
        }

        $result = $selection->delete();

        if ($result) {
            $this->emitter->emit(new UserMetaEvent($userId, $key, null));
        }
        return $result;
    }

    final public function userMeta($user)
    {
        return $this->userMetaRows($user)->fetchPairs('key', 'value');
    }

    final public function userMetaValueByKey(ActiveRow $user, string $key): ?string
    {
        $value = $this->userMetaRows($user)
            ->where('key = ?', $key)
            ->fetch()?->value;

        if (!$value) {
            return null;
        }
        return $value;
    }

    final public function userMetaRows($user)
    {
        if ($user instanceof ActiveRow) {
            $user = $user->id;
        }
        return $this->getTable()->where(['user_id' => $user])->order('key ASC');
    }

    final public function usersWithKey($key, $value = null): Selection
    {
        $users = $this->getTable()->where('key = ?', $key);
        if ($value) {
            $users->where('value = ?', $value);
        }
        return $users;
    }
}
