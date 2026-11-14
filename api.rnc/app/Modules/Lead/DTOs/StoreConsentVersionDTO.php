<?php

namespace App\Modules\Lead\DTOs;

use Spatie\LaravelData\Data;

class StoreConsentVersionDTO extends Data
{
    public function __construct(
        public string $text,
    ) {}
}
