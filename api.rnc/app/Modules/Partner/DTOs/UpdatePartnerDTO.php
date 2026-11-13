<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * An edit (`PATCH /api/partners/{id}`): only what the request sent. `active: false` is the
 * deactivation; an explicit null clears the phone or the city.
 */
class UpdatePartnerDTO extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $specialty,
        public string|Optional $email,
        public string|null|Optional $phone,
        public string|null|Optional $city,
        public bool|Optional $active,
    ) {}
}
