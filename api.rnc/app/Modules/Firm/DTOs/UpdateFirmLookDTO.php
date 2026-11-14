<?php

namespace App\Modules\Firm\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A firm's own look, edited by the operator (rnc-4 R25, D19): only what the request sent. An
 * explicit null clears the tagline or the proof (the site then shows no proof strip, C10). The slug,
 * the platform flag and the modules are not part of it.
 */
class UpdateFirmLookDTO extends Data
{
    /**
     * @param  list<array{value: string, label: string}>|null|Optional  $proof
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional $logo_text,
        public string|Optional $short_name,
        public string|null|Optional $tagline,
        public string|Optional $brand_primary,
        public string|Optional $brand_accent,
        public array|null|Optional $proof,
    ) {}
}
