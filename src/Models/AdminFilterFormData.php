<?php

namespace Crm\UsersModule\Models;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProviders\FilterUsersFormDataProviderInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\Selection;

class AdminFilterFormData
{
    private array $formData;

    public function __construct(
        private readonly AddressesRepository $addressesRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly UsersRepository $usersRepository
    ) {
    }

    public function parse(array $formData): void
    {
        $this->formData = $formData;
    }

    public function getFilteredUsers(): Selection
    {
        $users = $this->usersRepository
            ->all($this->getText())
            ->select('users.*')
            ->group('users.id');

        $users = $this->getAddressQuery($users);

        if ($this->getGroup()) {
            $users->where(':user_groups.group_id', (int)$this->getGroup());
        }
        if ($this->getSource()) {
            $users->where('users.source', $this->getSource());
        }

        /** @var FilterUsersFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.users_filter_form', FilterUsersFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $users = $provider->filter($users, $this->formData);
        }

        return $users;
    }

    public function getFormValues()
    {
        return [
            'text' => $this->getText(),
            'invoice' => $this->getInvoice(),
            'street' => $this->getStreet(),
            'number' => $this->getNumber(),
            'city' => $this->getCity(),
            'zip' => $this->getZip(),
            'phone' => $this->getPhone(),
            'group' => $this->getGroup(),
            'source' => $this->getSource()
        ];
    }

    private function getText()
    {
        return $this->formData['text'] ?? null;
    }

    private function getStreet()
    {
        return $this->formData['street'] ?? null;
    }

    private function getNumber()
    {
        return $this->formData['number'] ?? null;
    }

    private function getInvoice()
    {
        return $this->formData['invoice'] ?? null;
    }

    private function getCity()
    {
        return $this->formData['city'] ?? null;
    }

    private function getPhone()
    {
        return $this->formData['phone'] ?? null;
    }

    private function getZip()
    {
        return $this->formData['zip'] ?? null;
    }

    private function getGroup()
    {
        return $this->formData['group'] ?? null;
    }

    private function getSource()
    {
        return $this->formData['source'] ?? null;
    }

    private function getAddressQuery(Selection $users): Selection
    {
        $addresses = $this->addressesRepository->all()
            ->select('DISTINCT(user_id)');

        if ($invoice = $this->getInvoice()) {
            $addresses->where('company_id = ? OR company_tax_id = ? OR company_vat_id = ? OR company_name LIKE ?', [
                $invoice,
                $invoice,
                $invoice,
                "%{$invoice}%",
            ]);
        }

        if ($phone = $this->getPhone()) {
            $addresses->where('phone_number LIKE ?', "%{$phone}%");
        }

        if ($street = $this->getStreet()) {
            $addresses->where('street LIKE ?', "%{$street}%");
        }

        if ($number = $this->getNumber()) {
            $addresses->where('number LIKE ?', "%{$number}%");
        }

        if ($city = $this->getCity()) {
            $addresses->where('city LIKE ?', "%{$city}%");
        }

        if ($zip = $this->getZip()) {
            $addresses->where('zip LIKE ?', "%{$zip}%");
        }

        if ($invoice || $phone || $street || $number || $city || $zip) {
            return $users->where('users.id IN (?)', $addresses);
        }
        return $users;
    }
}
