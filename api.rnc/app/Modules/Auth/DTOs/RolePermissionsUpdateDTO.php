<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class RolePermissionsUpdateDTO extends Data
{
    public function __construct(
        public array $permissions,
    ) {}
}
