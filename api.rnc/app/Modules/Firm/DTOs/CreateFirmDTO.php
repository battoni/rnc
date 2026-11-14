<?php

namespace App\Modules\Firm\DTOs;

use Spatie\LaravelData\Data;

/**
 * A new firm and its manager (rnc-4 R21): what `firm:create` reads from its arguments and the
 * platform console (F4a) will read from its form. `FirmProvisioner` checks it.
 */
class CreateFirmDTO extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $manager,
        public ?string $email = null,
        public bool $specialist = false,
    ) {}
}
