<?php declare(strict_types=1);

namespace Crm\UsersModule\ViewObjects;

use Crm\ApplicationModule\Helpers\Arrayable;
use Crm\ApplicationModule\Helpers\ArrayableTrait;
use Nette\Database\Table\ActiveRow;

class Country implements Arrayable
{
    use ArrayableTrait;
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
            id: $country->id,
            name: $country->name,
            isoCode: $country->iso_code,
        );
    }
}
