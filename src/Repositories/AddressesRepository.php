<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\UsersModule\DataProviders\CanDeleteAddressDataProviderInterface;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class AddressesRepository extends Repository
{
    protected $tableName = 'addresses';

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    final public function add(
        ActiveRow $user,
        string $type,
        ?string $firstName,
        ?string $lastName,
        ?string $address,
        ?string $number,
        ?string $city,
        ?string $zip,
        ?int $countryId,
        ?string $phoneNumber,
        ?string $companyName = null,
        ?string $companyId = null,
        ?string $companyTaxId = null,
        ?string $companyVatId = null,
    ) {
        $companyId = $companyId ? preg_replace('/\s+/', '', $companyId) : null;
        $companyTaxId = $companyTaxId ? preg_replace('/\s+/', '', $companyTaxId) : null;
        $companyVatId = $companyVatId ? preg_replace('/\s+/', '', $companyVatId) : null;

        return $this->insert([
            'user_id' => $user->id,
            'type' => $type,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address' => $address,
            'number' => $number,
            'city' => $city,
            'zip' => $zip,
            'phone_number' => $phoneNumber,
            'country_id' => $countryId,
            'company_name' => $companyName,
            'company_id' => $companyId,
            'company_tax_id' => $companyTaxId,
            'company_vat_id' => $companyVatId,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function address(ActiveRow $user, string $type, ?bool $isDefault = null): ?ActiveRow
    {
        $selection = $this->getTable()
            ->where(['user_id' => $user->id, 'type' => $type])
            ->where('deleted_at IS NULL')
            ->order('updated_at DESC')->limit(1);

        if (isset($isDefault)) {
            $selection->where('is_default', $isDefault);
        }

        return $selection->fetch();
    }

    final public function all()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }

    final public function userAddresses(ActiveRow $user, string|array|null $type = null): Selection
    {
        $where = ['user_id' => $user->id];
        if ($type) {
            if (is_array($type)) {
                $where['type IN (?)'] = $type;
            } else {
                $where['type'] = $type;
            }
        }
        return $this->all()->where($where)->order('updated_at DESC');
    }

    final public function addresses(ActiveRow $user, $type = false): array
    {
        $where = ['user_id' => $user->id];
        if ($type) {
            $where['type'] = $type;
        }
        return $this->all()->where($where)->fetchAll();
    }



    /**
     * @deprecated
     * @see \Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder replacement
     */
    final public function addressesSelect(ActiveRow $user, $type): array
    {
        $rows = $this->addresses($user, $type);
        $result = [];
        foreach ($rows as $row) {
            $entries = [
                "{$row->first_name} {$row->last_name}",
                "{$row->address} {$row->number}",
                "{$row->zip} {$row->city}"
            ];
            $countryCode = $row->country?->iso_code;
            if ($countryCode) {
                $entries[] = $countryCode;
            }
            $result[$row->id] = "[{$row->type}] " . implode(", ", $entries);
        }
        return $result;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        if (isset($data['company_id'])) {
            $data['company_id'] = preg_replace('/\s+/', '', $data['company_id']);
        }
        if (isset($data['company_tax_id'])) {
            $data['company_tax_id'] = preg_replace('/\s+/', '', $data['company_tax_id']);
        }
        if (isset($data['company_vat_id'])) {
            $data['company_vat_id'] = preg_replace('/\s+/', '', $data['company_vat_id']);
        }
        return parent::update($row, $data);
    }

    final public function findByAddress($address, $type, $userId)
    {
        $addressMap = [
            'first_name' => null,
            'last_name' => null,
            'address' => null,
            'number' => null,
            'city' => null,
            'zip' => null,
            'country_id' => null,
            'company_id' => null,
            'company_tax_id' => null,
            'company_vat_id' => null,
            'company_name' => null,
            'phone_number' => null,
            'type' => $type,
            'user_id' => $userId,
        ];

        foreach ($address as $key => $value) {
            if (array_key_exists($key, $addressMap)) {
                $addressMap[$key] = $value;
            }
        }

        return $this->getTable()->where($addressMap)->where('deleted_at IS NULL')->fetch();
    }

    /**
     * @param ActiveRow $address
     * @return array
     * @throws DataProviderException
     */
    public function canDelete(ActiveRow $address)
    {
        /** @var CanDeleteAddressDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'users.dataprovider.address.can_delete',
            CanDeleteAddressDataProviderInterface::class
        );
        foreach ($providers as $sorting => $provider) {
            $result = $provider->provide([
                'address' => $address
            ]);

            if (isset($result['canDelete']) && $result['canDelete'] === false) {
                return $result;
            }
        }

        return [
            'canDelete' => true
        ];
    }

    /**
     * @param ActiveRow $address
     * @param bool $force
     * @throws \Exception
     */
    public function softDelete(ActiveRow $address, $force = false)
    {
        if (!$force) {
            $check = $this->canDelete($address);
            if ($check['canDelete'] === false) {
                throw new CantDeleteAddressException($check['message']);
            }
        }

        $this->update($address, [
            'deleted_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }
}
