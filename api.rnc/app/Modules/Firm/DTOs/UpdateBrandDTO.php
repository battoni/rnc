<?php

namespace App\Modules\Firm\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A sub-brand's edit (`PATCH /api/brands/{id}`): only what the request sent. An explicit null
 * clears the tagline.
 */
class UpdateBrandDTO extends Data
{
    /**
     * @param  list<array{value: string, label: string}>|Optional  $proof
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional $short_name,
        public string|null|Optional $tagline,
        public string|Optional $brand_primary,
        public string|Optional $brand_accent,
        public array|Optional $proof,
    ) {}
}
