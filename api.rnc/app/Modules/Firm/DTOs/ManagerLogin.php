<?php

namespace App\Modules\Firm\DTOs;

use App\Models\User;

/**
 * A manager login the console made or reset (rnc-4 R25): the account, its one-time password — shown
 * once by whoever asked and stored nowhere but as a hash — and whether the account is new.
 */
final readonly class ManagerLogin
{
    public function __construct(
        public User $manager,
        public string $password,
        public bool $created,
    ) {}
}
