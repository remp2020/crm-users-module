<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class AddressesMetaRepository extends Repository
{
    protected $tableName = 'addresses_meta';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     * @param string $value
     * @param bool $override
     * @throws \Exception
     */
    final public function add(ActiveRow $address, ?ActiveRow $addressChangeRequest, $key, $value, $override = true): ActiveRow
    {
        if ($override && $this->exists($address, $addressChangeRequest, $key)) {
            $meta = $this->getTable()->where([
                'address_id' => $address->id,
                'address_change_request_id' => $addressChangeRequest?->id,
                'key' => $key,
            ])->fetch();
            if ($meta) {
                if ($meta->value !== $value) {
                    $this->update($meta, [
                        'value' => $value,
                        'updated_at' => new \DateTime(),
                    ]);
                }
                return $meta;
            }
        }
        return $this->insert([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest?->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function all(): Selection
    {
        return $this->getTable()->where('address.deleted_at IS NULL');
    }

    final public function values(ActiveRow $address, ?ActiveRow $addressChangeRequest, ...$keys): Selection
    {
        $values = $this->getTable()->where([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest->id ?? null,
        ]);
        if (!empty($keys)) {
            $values->where(['key' => $keys]);
        }
        return $values;
    }

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     */
    final public function exists(ActiveRow $address, ?ActiveRow $addressChangeRequest, $key): bool
    {
        return $this->getTable()->where([
                'address_id' => $address->id,
                'address_change_request_id' => $addressChangeRequest->id ?? null,
                'key' => $key,
            ])->count('*') > 0;
    }

    /**
     * @param ActiveRow $address
     * @param ActiveRow|null $addressChangeRequest
     * @param string $key
     */
    final public function remove(ActiveRow $address, ?ActiveRow $addressChangeRequest, $key): int
    {
        return $this->getTable()->where([
            'address_id' => $address->id,
            'address_change_request_id' => $addressChangeRequest->id ?? null,
            'key' => $key,
        ])->delete();
    }

    final public function deleteByAddressChangeRequestId($addressChangeRequestId): void
    {
        $records = $this->getTable()->where('address_change_request_id = ?', $addressChangeRequestId);
        foreach ($records as $record) {
            $this->delete($record);
        }
    }
}
