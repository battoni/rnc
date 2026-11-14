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
        /** Set by `firm:create` (rnc-4 F3b1); `StoreUserRequest` has no rule for it, so the form never sends one. */
        public ?string $email = null,
        /** From the form (rnc-4 F5a, R31) and from `firm:create --specialist` (F3b1). */
        public bool $is_specialist = false,
    ) {}
}
