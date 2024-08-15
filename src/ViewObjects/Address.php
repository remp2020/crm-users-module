<?php
declare(strict_types=1);

namespace Crm\UsersModule\ViewObjects;

use Nette\Database\Table\ActiveRow;

class Address
{
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
            $address->id,
            $address->type,
            $address->first_name,
            $address->last_name,
            $address->address,
            $address->number,
            $address->city,
            $address->zip,
            $country,
            $address->phone_number,
            $address->company_name,
            $address->company_id,
            $address->company_tax_id,
            $address->company_vat_id
        );
    }
}
