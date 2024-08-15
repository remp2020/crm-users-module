<?php
declare(strict_types=1);

namespace Crm\UsersModule\ViewObjects;

use Crm\ApplicationModule\Helpers\Arrayable;
use Crm\ApplicationModule\Helpers\ArrayableTrait;
use Nette\Database\Table\ActiveRow;

class Address implements Arrayable
{
    use ArrayableTrait;

    /**
     * We encourage the use of named arguments to avoid future breaking changes in extensibility.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $type,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $address,
        public readonly ?string $number,
        public readonly ?string $city,
        public readonly ?string $zip,
        public readonly ?Country $country,
        public readonly ?string $phoneNumber,
        public readonly ?string $companyName,
        public readonly ?string $companyId,
        public readonly ?string $companyTaxId,
        public readonly ?string $companyVatId,
    ) {
    }

    public static function fromActiveRow(ActiveRow $address): self
    {
        $country = $address->country
            ? Country::fromActiveRow($address->country)
            : null;

        return new self(
            id: $address->id,
            type: $address->type,
            firstName: $address->first_name,
            lastName: $address->last_name,
            address: $address->address,
            number: $address->number,
            city: $address->city,
            zip: $address->zip,
            country: $country,
            phoneNumber: $address->phone_number,
            companyName: $address->company_name,
            companyId: $address->company_id,
            companyTaxId: $address->company_tax_id,
            companyVatId: $address->company_vat_id,
        );
    }

    public function formatSimpleWithType(): string
    {
        return "[{$this->type}] " . $this->formatSimple();
    }

    public function formatSimple(): string
    {
        $entries = [
            "{$this->firstName} {$this->lastName}",
            "{$this->address} {$this->number}",
            "{$this->zip} {$this->city}",
        ];

        if ($this->country) {
            $entries[] = $this->country->isoCode;
        }
        return implode(", ", $entries);
    }
}
