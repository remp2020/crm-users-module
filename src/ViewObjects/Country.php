<?php declare(strict_types=1);

namespace Crm\UsersModule\ViewObjects;

use Nette\Database\Table\ActiveRow;

class Country
{
    /**
     * We encourage the use of named arguments to avoid future breaking changes in extensibility.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $isoCode,
    ) {
    }

    public static function fromActiveRow(ActiveRow $country): self
    {
        return new self(
            $country->id,
            $country->name,
            $country->iso_code,
        );
    }
}
