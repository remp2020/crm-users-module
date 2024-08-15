<?php
declare(strict_types=1);

namespace Crm\UsersModule\Tests\ViewObjects;

use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\UsersModule\ViewObjects\Country;

class CountryTest extends CrmTestCase
{
    private ActiveRowFactory $activeRowFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testFromActiveRow(): void
    {
        $countryRow = $this->activeRowFactory->create([
            'id' => 1,
            'name' => 'Slovakia',
            'iso_code' => 'SK',
        ]);

        $country = Country::fromActiveRow($countryRow);
        $this->assertSame(1, $country->id);
        $this->assertSame('Slovakia', $country->name);
        $this->assertSame('SK', $country->isoCode);
    }
}
