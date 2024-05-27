<?php
declare(strict_types=1);

namespace Crm\UsersModule\Models;

class Config
{
    public function __construct(
        public readonly bool $showFullAddress,
    ) {
    }
}
