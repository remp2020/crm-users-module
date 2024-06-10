<?php
declare(strict_types=1);

namespace Crm\UsersModule\Tests\ViewObjects;

use Crm\ApplicationModule\Models\Database\Selection;
use Crm\UsersModule\ViewObjects\Address;
use Nette\Database\Table\ActiveRow;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testFromActiveRow(): void
    {
        $addressData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => 'Main Street',
            'number' => '123',
            'city' => 'New York',
            'zip' => '10001',
            'country_id' => 1,
            'phone_number' => '+421000000000',
            'company_name' => 'Some Company',
            'company_id' => '12345678',
            'company_tax_id' => '1234567890',
            'company_vat_id' => 'SK1234567890',
        ];

        $selection = $this->createMock(Selection::class);

        $address = Address::fromActiveRow(new ActiveRow($addressData, $selection));

        $this->assertSame('John', $address->firstName);
        $this->assertSame('Doe', $address->lastName);
        $this->assertSame('Main Street', $address->address);
        $this->assertSame('123', $address->number);
        $this->assertSame('New York', $address->city);
        $this->assertSame('10001', $address->zip);
        $this->assertSame(1, $address->countryId);
        $this->assertSame('+421000000000', $address->phoneNumber);
        $this->assertSame('Some Company', $address->companyName);
        $this->assertSame('12345678', $address->companyId);
        $this->assertSame('1234567890', $address->companyTaxId);
        $this->assertSame('SK1234567890', $address->companyVatId);
    }
}
