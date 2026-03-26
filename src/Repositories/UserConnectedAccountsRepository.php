<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;
use Tracy\Debugger;

class UserConnectedAccountsRepository extends Repository
{
    public const TYPE_APPLE_SIGN_IN = 'apple_sign_in';

    public const TYPE_GOOGLE_SIGN_IN = 'google_sign_in';

    protected $tableName = 'user_connected_accounts';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(
        ActiveRow $user,
        string $type,
        string $externalId,
        ?string $email,
        mixed $meta = null,
    ) {
        if ($meta && !is_string($meta)) {
            $meta = Json::encode($meta);
        }

        return $this->insert([
            'user_id' => $user->id,
            'external_id' => $externalId,
            'email' => $email,
            'type' => $type,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
            'meta' => $meta,
        ]);
    }

    final public function getByExternalId(string $type, string $externalId)
    {
        return $this->getTable()->where([
            'external_id' => $externalId,
            'type' => $type,
        ])->fetch();
    }

    final public function getForUser(ActiveRow $user, string $type): Selection
    {
        return $this->getTable()->where([
            'user_id' => $user->id,
            'type' => $type,
        ]);
    }

    public function removeAccountsForUser(ActiveRow $user): int
    {
        $userAccounts = $this->getTable()
            ->where(['user_id' => $user->id])
            ->fetchAll();

        $removed = 0;
        foreach ($userAccounts as $userAccount) {
            $result = $this->delete($userAccount);
            if ($result !== true) {
                Debugger::log("Unable to remove connect account ID [{$userAccount->id}] for user [{$user->id}].", Debugger::ERROR);
            }
            $this->markAuditLogsForDelete($userAccount->getSignature());
            $removed++;
        }

        return $removed;
    }

    public function removeAccountForUser(ActiveRow $user, int $id): ?bool
    {
        $userAccount = $this->getTable()
            ->where([
                'user_id' => $user->id,
                'id' => $id,
            ])
            ->fetch();

        if (!$userAccount) {
            return null;
        }

        return $this->delete($userAccount);
    }

    public function connectUser(ActiveRow $user, $type, $externalId, $email, mixed $meta = null)
    {
        $connectedAccount = $this->getForUser($user, $type)
            ->where('external_id', $externalId)
            ->fetch();

        if (!$connectedAccount) {
            $connectedAccount = $this->add(
                $user,
                $type,
                $externalId,
                $email,
                $meta,
            );
        }

        return $connectedAccount;
    }
}
