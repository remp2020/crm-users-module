<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\NewAddressChangeRequestEvent;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Models\AddressChangeRequest\StatusEnum;
use Exception;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Emitter as HermesEmitter;

class AddressChangeRequestsRepository extends Repository
{
    /** @deprecated Use \Crm\UsersModule\Models\AddressChangeRequest\StatusEnum::New enum instead. */
    const STATUS_NEW = 'new';
    /** @deprecated Use \Crm\UsersModule\Models\AddressChangeRequest\StatusEnum::Accepted enum instead. */
    const STATUS_ACCEPTED = 'accepted';
    /** @deprecated Use \Crm\UsersModule\Models\AddressChangeRequest\StatusEnum::Rejected enum instead. */
    const STATUS_REJECTED = 'rejected';

    protected $tableName = 'address_change_requests';

    public function __construct(
        Explorer $database,
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressesMetaRepository $addressesMetaRepository,
        private readonly Emitter $emitter,
        private readonly HermesEmitter $hermesEmitter
    ) {
        parent::__construct($database);
    }

    final public function add(
        ActiveRow $user,
        $parentAddress,
        ?string $firstName,
        ?string $lastName,
        ?string $companyName,
        ?string $address,
        ?string $number,
        ?string $city,
        ?string $zip,
        ?int $countryId,
        ?string $companyId,
        ?string $companyTaxId,
        ?string $companyVatId,
        ?string $phoneNumber,
        ?string $type = null,
    ) {
        $isDifferent = false;
        if (!$parentAddress || ($firstName != $parentAddress->first_name ||
            $lastName !== $parentAddress->last_name ||
            $phoneNumber !== $parentAddress->phone_number ||
            $address !== $parentAddress->address||
            $number !== $parentAddress->number ||
            $city !== $parentAddress->city ||
            $zip !== $parentAddress->zip ||
            $countryId !== $parentAddress->country_id ||
            $companyName !== $parentAddress->company_name ||
            $companyId !== $parentAddress->company_id ||
            $companyTaxId !== $parentAddress->company_tax_id ||
            $companyVatId !== $parentAddress->company_vat_id
            )
        ) {
            $isDifferent = true;
        }

        if (!$isDifferent) {
            return false;
        }

        if ($parentAddress) {
            $type = $parentAddress->type;
        }

        $companyId = $companyId ? preg_replace('/\s+/', '', $companyId) : null;
        $companyTaxId = $companyTaxId ? preg_replace('/\s+/', '', $companyTaxId) : null;
        $companyVatId = $companyVatId ? preg_replace('/\s+/', '', $companyVatId) : null;

        /** @var ActiveRow $changeRequest */
        $changeRequest = $this->insert([
            'type' => $type,
            'user_id' => $user->id,
            'address_id' => $parentAddress ? $parentAddress->id : null,
            'status' => StatusEnum::New->value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'address' => $address,
            'number' => $number,
            'city' => $city,
            'zip' => $zip,
            'country_id' => $countryId,
            'company_id' => $companyId,
            'company_tax_id' => $companyTaxId,
            'company_vat_id' => $companyVatId,
            'phone_number' => $phoneNumber,
            'old_first_name' => $parentAddress ? $parentAddress->first_name : null,
            'old_last_name' => $parentAddress ? $parentAddress->last_name : null,
            'old_company_name' => $parentAddress ? $parentAddress->company_name : null,
            'old_address' => $parentAddress ? $parentAddress->address : null,
            'old_number' => $parentAddress ? $parentAddress->number : null,
            'old_city' => $parentAddress ? $parentAddress->city : null,
            'old_zip' => $parentAddress ? $parentAddress->zip : null,
            'old_country_id' => $parentAddress ? $parentAddress->country_id : null,
            'old_company_id' => $parentAddress ? $parentAddress->company_id : null,
            'old_company_tax_id' => $parentAddress ? $parentAddress->company_tax_id : null,
            'old_company_vat_id' => $parentAddress ? $parentAddress->company_vat_id : null,
            'old_phone_number' => $parentAddress ? $parentAddress->phone_number : null,
        ]);

        $this->emitter->emit(new NewAddressChangeRequestEvent($changeRequest));

        // Reload changes caused by the event
        return $this->getTable()->where('id', $changeRequest->id)->fetch();
    }

    final public function changeStatus(ActiveRow $addressChangeRequest, $status)
    {
        return $this->update($addressChangeRequest, [
            'updated_at' => new \DateTime(),
            'status' => $status,
        ]);
    }

    final public function acceptRequest(ActiveRow $addressChangeRequest, $asAdmin = false)
    {
        $address = $addressChangeRequest->addr;
        if ($addressChangeRequest->status === StatusEnum::Accepted->value) {
            if ($address === null) {
                throw new Exception(sprintf(
                    'Invalid state. Address change request #%d has already been accepted but address is missing.',
                    $addressChangeRequest->id,
                ));
            }

            return $address;
        }

        if ($addressChangeRequest->status === StatusEnum::Rejected->value) {
            throw new Exception(sprintf(
                'Address change request #%d has already been rejected.',
                $addressChangeRequest->id,
            ));
        }

        if ($addressChangeRequest->status !== StatusEnum::New->value) {
            throw new Exception(sprintf(
                'Address change request #%d must be in status "new" to be accepted. Current status: %s',
                $addressChangeRequest->id,
                $addressChangeRequest->status,
            ));
        }

        if ($address) {
            $this->addressesRepository->update($address, [
                'first_name' => $addressChangeRequest['first_name'],
                'last_name' => $addressChangeRequest['last_name'],
                'company_name' => $addressChangeRequest['company_name'],
                'phone_number' => $addressChangeRequest['phone_number'],
                'address' => $addressChangeRequest['address'],
                'number' => $addressChangeRequest['number'],
                'city' => $addressChangeRequest['city'],
                'zip' => $addressChangeRequest['zip'],
                'country_id' => $addressChangeRequest['country_id'],
                'company_id' => $addressChangeRequest['company_id'],
                'company_tax_id' => $addressChangeRequest['company_tax_id'],
                'company_vat_id' => $addressChangeRequest['company_vat_id'],
                'updated_at' => new DateTime(),
            ]);
            $this->emitter->emit(new AddressChangedEvent($address, $asAdmin));
            $this->hermesEmitter->emit(new HermesMessage('address-changed', [
                'address_id' => $address->id
            ]));
        } else {
            /** @var ActiveRow $address */
            $address = $this->addressesRepository->add(
                $addressChangeRequest->user,
                $addressChangeRequest->type,
                $addressChangeRequest->first_name,
                $addressChangeRequest->last_name,
                $addressChangeRequest->address,
                $addressChangeRequest->number,
                $addressChangeRequest->city,
                $addressChangeRequest->zip,
                $addressChangeRequest->country_id,
                $addressChangeRequest->phone_number,
                $addressChangeRequest->company_name,
                $addressChangeRequest->company_id,
                $addressChangeRequest->company_tax_id,
                $addressChangeRequest->company_vat_id,
            );
            $this->emitter->emit(new NewAddressEvent($address, $asAdmin));
            $this->hermesEmitter->emit(new HermesMessage('new-address', [
                'address_id' => $address->id
            ]));
        }

        if (!$addressChangeRequest->address_id) {
            $this->update($addressChangeRequest, [
                'address_id' => $address->id
            ]);
        }
        $this->changeStatus($addressChangeRequest, StatusEnum::Accepted->value);
        return $address;
    }

    final public function rejectRequest(ActiveRow $addressChangeRequest)
    {
        return $this->changeStatus($addressChangeRequest, StatusEnum::Rejected->value);
    }

    final public function allNewRequests()
    {
        return $this->getTable()
            ->where(['status' => StatusEnum::New->value])
            ->where('address.deleted_at IS NULL')
            ->order('created_at DESC');
    }

    final public function all()
    {
        return $this->getTable()
            ->where('address.deleted_at IS NULL')
            ->order('created_at DESC');
    }

    final public function userRequests($userId)
    {
        return $this->all()->where('address_change_requests.user_id', $userId);
    }

    final public function deleteAll($userId)
    {
        foreach ($this->userRequests($userId) as $addressChange) {
            $this->addressesMetaRepository->deleteByAddressChangeRequestId($addressChange->id);
            $this->delete($addressChange);
            $this->markAuditLogsForDelete($addressChange->getSignature());
        }
    }

    final public function lastAcceptedForAddress($addressId)
    {
        return $this->getTable()
            ->where([
                'status' => AddressChangeRequestsRepository::STATUS_ACCEPTED,
                'address_id' => $addressId,
            ])
            ->order('updated_at DESC')
            ->limit(1)
            ->fetch();
    }
}
