<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\ResettableInterface;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class CountriesRepository extends Repository implements ResettableInterface
{
    protected $tableName = 'countries';

    private ?ActiveRow $defaultCountry;

    private string $defaultCountryISO;

    public function __construct(string $defaultCountryISO, Explorer $database)
    {
        parent::__construct($database);
        $this->setDefaultCountry($defaultCountryISO);
    }

    final public function setDefaultCountry(string $defaultCountryISO): void
    {
        if (empty(trim($defaultCountryISO))) {
            throw new \Exception("Unable to load default country from empty string.");
        }
        $this->defaultCountryISO = $defaultCountryISO;
    }

    final public function defaultCountry(): ActiveRow
    {
        if (!isset($this->defaultCountryISO)) {
            throw new \Exception("Unable to load default country. Use `setDefaultCountry()`.");
        }
        if (!isset($this->defaultCountry)) {
            $defaultCountry = $this->findByIsoCode($this->defaultCountryISO);
            if ($defaultCountry === null) {
                throw new \Exception("Unable to load default country from provided ISO code [{$this->defaultCountryISO}].");
            }
            $this->defaultCountry = $defaultCountry;
        }
        return $this->defaultCountry;
    }

    final public function all()
    {
        return $this->getTable()->order('-sorting DESC, name');
    }

    final public function getAllPairs()
    {
        return $this->all()->fetchPairs('id', 'name');
    }

    final public function getAllIsoPairs()
    {
        return $this->all()->fetchPairs('id', 'iso_code');
    }

    final public function getDefaultCountryPair()
    {
        $default = $this->defaultCountry();
        return [$default->id => $default->name];
    }

    final public function findByName($countryName)
    {
        return $this->findBy('name', $countryName);
    }

    final public function findByIsoCode($isoCode)
    {
        return $this->findBy('iso_code', $isoCode);
    }

    final public function add(string $isoCode, string $name, ?int $sorting)
    {
        return $this->insert([
            'iso_code' => $isoCode,
            'name' => $name,
            'sorting' => $sorting
        ]);
    }

    final public function exists($code)
    {
        return $this->getTable()->where('iso_code', $code)->count('*') > 0;
    }

    public function reset(): void
    {
        $this->defaultCountry = null;
    }
}
