<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

/**
 * An account another module opens for one of its own records (rnc-2 R1: a partner's portal
 * login). Auth owns `users`, so the Partner module asks for the row instead of writing it.
 */
class LinkedAccountDTO extends Data
{
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public int $firmId,
        public int $partnerId,
        public string $roleSlug,
    ) {}
}
