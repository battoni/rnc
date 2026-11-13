<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;

/** A partner's portal login (`POST /api/partners/{id}/access`, R2): the password is generated. */
class CreatePartnerAccessDTO extends Data
{
    public function __construct(
        public string $username,
        public string $email,
    ) {}
}
