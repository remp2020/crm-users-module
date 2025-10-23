<?php

declare(strict_types=1);

namespace Crm\UsersModule\Components\UserAddressListWidget;

class UserAddressListConfig
{
    /** @var array<string, string> Maps address type to Font Awesome icon class (e.g., 'invoice' => 'fa-file-lines') */
    private array $iconMapping = [];

    /** @var array<string> List of address types that are always visible in the widget */
    private array $alwaysVisibleTypes = [];

    /**
     * @return array<string, string> Address type to icon class mapping
     */
    public function getIconMapping(): array
    {
        return $this->iconMapping;
    }

    /**
     * Add icon mapping for a specific address type.
     */
    public function addIconMapping(string $addressType, string $faIcon): void
    {
        $this->iconMapping[$addressType] = $faIcon;
    }

    /**
     * @return array<string> List of address types that are always visible
     */
    public function getAlwaysVisibleTypes(): array
    {
        return $this->alwaysVisibleTypes;
    }

    /**
     * Set address types that are always visible (replaces existing list).
     */
    public function setAlwaysVisibleTypes(string ...$types): void
    {
        $this->alwaysVisibleTypes = $types;
    }

    /**
     * Add address types to the always visible list.
     */
    public function addAlwaysVisibleTypes(string ...$addressTypes): void
    {
        $this->alwaysVisibleTypes = array_unique(array_merge($this->alwaysVisibleTypes, $addressTypes));
    }
}
