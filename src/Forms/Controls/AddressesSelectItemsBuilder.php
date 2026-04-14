<?php
declare(strict_types=1);

namespace Crm\UsersModule\Forms\Controls;

use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\ViewObjects\Address;
use Nette\Database\Table\ActiveRow;

final class AddressesSelectItemsBuilder
{
    public function __construct(
        public readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function buildSimple(
        ActiveRow $user,
        string|array|null $type = null,
        ?ActiveRow $country = null,
        bool $requireFullAddress = false,
    ): array {
        $rows = $this->addressesRepository->userAddresses($user, $type);
        if ($country !== null) {
            $rows->where(['country_id' => $country->id]);
        }
        if ($requireFullAddress) {
            $rows->where([
                'street IS NOT NULL AND TRIM(street) != ""',
                'number IS NOT NULL AND TRIM(number) != ""',
                'city IS NOT NULL AND TRIM(city) != ""',
                'zip IS NOT NULL AND TRIM(zip) != ""',
                'phone_number IS NOT NULL AND TRIM(phone_number) != ""',
                'country_id IS NOT NULL',
            ]);
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] = Address::fromActiveRow($row)->formatSimple();
        }
        return $result;
    }

    public function buildSimpleWithTypes(ActiveRow $user, string|array|null $type = null): array
    {
        $rows = $this->addressesRepository->userAddresses($user, $type);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] = Address::fromActiveRow($row)->formatSimpleWithType();
        }
        return $result;
    }
}
