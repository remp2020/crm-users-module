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

    public function buildSimple(ActiveRow $user, string|array|null $type = null): array
    {
        $rows = $this->addressesRepository->userAddresses($user, $type);
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
