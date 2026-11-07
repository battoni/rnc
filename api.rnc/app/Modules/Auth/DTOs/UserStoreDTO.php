<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class UserStoreDTO extends Data
{
    public function __construct(
        public string $name,
        public string $last_name,
        public string $username,
        public int $role_id,
        public string $status,
        public string $password,
        /** @var array<string, bool> */
        public array $permissions = [],
    ) {}
}
