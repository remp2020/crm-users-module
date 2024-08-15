<?php
declare(strict_types=1);

namespace Crm\UsersModule\Tests\ViewObjects;

use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\UsersModule\ViewObjects\Address;

class AddressTest extends CrmTestCase
{
    private ActiveRowFactory $activeRowFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testFromActiveRow(): void
    {
        $country = $this->activeRowFactory->create([
            'id' => 1,
            'name' => 'Slovakia',
            'iso_code' => 'SK',
        ]);

        $addressRow = $this->activeRowFactory->create([
            'id' => 1,
            'type' => 'invoice',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => 'Main Street',
            'number' => '123',
            'city' => 'New York',
            'zip' => '10001',
            'country' => $country,
            'phone_number' => '+421000000000',
            'company_name' => 'Some Company',
            'company_id' => '12345678',
            'company_tax_id' => '1234567890',
            'company_vat_id' => 'SK1234567890',
        ]);

        $address = Address::fromActiveRow($addressRow);

        $this->assertSame(1, $address->id);
        $this->assertSame('invoice', $address->type);
        $this->assertSame('John', $address->firstName);
        $this->assertSame('Doe', $address->lastName);
        $this->assertSame('Main Street', $address->address);
        $this->assertSame('123', $address->number);
        $this->assertSame('New York', $address->city);
        $this->assertSame('10001', $address->zip);
        $this->assertSame('+421000000000', $address->phoneNumber);
        $this->assertSame('Some Company', $address->companyName);
        $this->assertSame('12345678', $address->companyId);
        $this->assertSame('1234567890', $address->companyTaxId);
        $this->assertSame('SK1234567890', $address->companyVatId);

        $this->assertNotNull($address->country);
        $this->assertSame(1, $address->country->id);
        $this->assertSame('Slovakia', $address->country->name);
        $this->assertSame('SK', $address->country->isoCode);
    }

    public function testFromActiveRowWithoutCountry(): void
    {
        $addressRow = $this->activeRowFactory->create([
            'id' => 1,
            'type' => 'invoice',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => 'Main Street',
            'number' => '123',
            'city' => 'New York',
            'zip' => '10001',
            'country' => null,
            'phone_number' => '+421000000000',
            'company_name' => 'Some Company',
            'company_id' => '12345678',
            'company_tax_id' => '1234567890',
            'company_vat_id' => 'SK1234567890',
        ]);

        $address = Address::fromActiveRow($addressRow);
        $this->assertNull($address->country);
    }
}
