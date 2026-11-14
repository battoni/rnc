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
        /** rnc-4 F5a (R31, R32): newly true makes the person bookable on the firm's hours. */
        public bool|Optional $is_specialist,
    ) {}
}
