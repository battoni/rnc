<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserUpdateDTO extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $last_name,
        public string|Optional $username,
        public Optional|null|int $role_id,
        public string|Optional $status,
        public Optional|null|string $password,
        /** @var array<string, bool> */
        public array|Optional $permissions,
    ) {}
}
