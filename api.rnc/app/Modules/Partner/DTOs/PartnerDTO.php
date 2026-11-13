<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;

/** A new partner (`POST /api/partners`). */
class PartnerDTO extends Data
{
    public function __construct(
        public string $name,
        public string $specialty,
        public string $email,
        public ?string $phone = null,
        public ?string $city = null,
    ) {}
}
