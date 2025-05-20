<?php

namespace Crm\UsersModule\Tests\Repositories;

use Crm\ApplicationModule\Database\DatabaseTransaction;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Explorer;

class CountriesRepositoryTest extends DatabaseTestCase
{
    public CountriesRepository $countriesRepository;

    protected function requiredRepositories(): array
    {
        return [
            CountriesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDefaultCountrySuccess()
    {
        // default country set by config.neon with `users.countries.default`; DI loads it from there
        $testCountry = 'SK';
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->defaultCountry();
        $this->assertEquals($testCountry, $country->iso_code);

        // default country set while initializing repository
        $testCountry = 'CZ';
        $countriesRepository = new CountriesRepository($testCountry, $this->inject(Explorer::class));
        $countriesRepository->setTransaction($this->inject(DatabaseTransaction::class));
        $country = $countriesRepository->defaultCountry();
        $this->assertEquals($testCountry, $country->iso_code);
    }

    public function testDefaultCountryCountryIsoEmptyFailure()
    {
        $testCountry = '';
        $this->expectExceptionObject(
            new \Exception("Unable to load default country from empty string."),
        );
        $_ = new CountriesRepository($testCountry, $this->inject(Explorer::class));
    }

    public function testDefaultCountryCountryIsoUnknownFailure()
    {
        $testCountry = 'UNKNOWN';
        $this->expectExceptionObject(
            new \Exception("Unable to load default country from provided ISO code [UNKNOWN]."),
        );
        $countriesRepository = new CountriesRepository($testCountry, $this->inject(Explorer::class));
        $countriesRepository->setTransaction($this->inject(DatabaseTransaction::class));
        $countriesRepository->defaultCountry();
    }
}
