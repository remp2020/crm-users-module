<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\UsersModule\Repositories\AddressesRepository;

class AddressesUserDataProvider implements UserDataProviderInterface
{
    use NowTrait;

    private $addressesRepository;

    public function __construct(
        AddressesRepository $addressesRepository
    ) {
        $this->addressesRepository = $addressesRepository;
    }

    public static function identifier(): string
    {
        return 'addresses';
    }

    public static function gdprRemovalTemplate($deletedAt)
    {
        return [
            'title' => 'GDPR removal',
            'first_name' => 'GDPR removal',
            'last_name' => 'GDPR removal',
            'street' => 'GDPR removal',
            'number' => 'GDPR removal',
            'city' => 'GDPR removal',
            'zip' => 'GDPR removal',
            'country_id' => null,
            'company_id' => 'GDPR removal',
            'company_tax_id' => 'GDPR removal',
            'company_vat_id' => 'GDPR removal',
            'company_name' => 'GDPR removal',
            'phone_number' => 'GDPR removal',
            'deleted_at' => $deletedAt
        ];
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        $addresses = $this->addressesRepository->getTable()->where(['user_id' => $userId])->fetchAll();

        if (!$addresses) {
            return [];
        }

        $returnAddresses = [];
        foreach ($addresses as $address) {
            $returnAddress = [
                'type' => $address->type,
                'created_at' => $address->created_at->format(\DateTime::RFC3339),
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'street' => $address->street,
                'number' => $address->number,
                'city' => $address->city,
                'zip' => $address->zip,
                'phone_number' => $address->phone_number,
            ];

            if ($address->country) {
                $returnAddress['country'] = $address->country->name;
            }
            if (!empty($address->company_id)) {
                $returnAddress['company_id'] = $address->company_id;
            }
            if (!empty($address->company_tax_id)) {
                $returnAddress['company_tax_id'] = $address->company_tax_id;
            }
            if (!empty($address->company_vat_id)) {
                $returnAddress['company_vat_id'] = $address->company_vat_id;
            }
            if (!empty($address->company_name)) {
                $returnAddress['company_name'] = $address->company_name;
            }

            $returnAddresses[] = $returnAddress;
        }

        return $returnAddresses;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    /**
     * @param $userId
     * @throws \Exception
     */
    public function delete($userId, $protectedData = [])
    {
        $query = $this->addressesRepository->getTable()->where(['user_id' => $userId]);
        if (count($protectedData) > 0) {
            $query = $query->where('id NOT IN (?)', $protectedData);
        }

        $addresses = $query->fetchAll();
        $gdprRemovalTemplate = self::gdprRemovalTemplate($this->getNow());
        foreach ($addresses as $address) {
            $this->addressesRepository->update($address, $gdprRemovalTemplate);
            $this->addressesRepository->markAuditLogsForDelete($address->getSignature());
        }
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
