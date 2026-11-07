<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class RoleUpdateDTO extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $permission_group_ids = null,
    ) {}
}
