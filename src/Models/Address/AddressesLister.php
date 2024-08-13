<?php
declare(strict_types=1);

namespace Crm\UsersModule\Models\Address;

use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;

final class AddressesLister
{
    public const FORMAT_FULL_WITH_TYPE = 'full_with_type';
    public const FORMAT_FULL_WITHOUT_TYPE = 'full_without_type';

    public function __construct(
        public readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function addressesForSelect(
        ActiveRow $user,
        string|array $types = null,
        $format = self::FORMAT_FULL_WITH_TYPE
    ): array {
        $rows = $this->addressesRepository->userAddresses($user, $types);
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
            $result[$row->id] = match ($format) {
                self::FORMAT_FULL_WITH_TYPE => "[{$row->type}] " . implode(", ", $entries),
                self::FORMAT_FULL_WITHOUT_TYPE => implode(", ", $entries),
                default => throw new \RuntimeException("Invalid format [{$format}]"),
            };
        }
        return $result;
    }
}
