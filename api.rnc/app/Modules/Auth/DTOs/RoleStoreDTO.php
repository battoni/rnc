<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class RoleStoreDTO extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public array $permission_group_ids,
    ) {}
}
