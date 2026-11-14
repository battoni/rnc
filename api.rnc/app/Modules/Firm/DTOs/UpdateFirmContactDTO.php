<?php

namespace App\Modules\Firm\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The firm's contact data, edited by its manager (rnc-4 R30, D25): only what the request sent; an
 * explicit null clears the field. The look, the name, the slug and the modules are not part of it —
 * they are the operator's (`UpdateFirmLookDTO`, `FirmModulesService`).
 */
class UpdateFirmContactDTO extends Data
{
    /**
     * @param  list<array{weekdays: list<int>, from: string, to: string}>|Optional  $business_hours
     */
    public function __construct(
        public string|null|Optional $address,
        public string|null|Optional $phone,
        public string|null|Optional $mailbox,
        public array|Optional $business_hours,
        public string|null|Optional $credentials,
    ) {}
}
