<?php

namespace App\Modules\Portfolio\DTOs;

use Spatie\LaravelData\Data;

/** The repricing list's filters (`GET /api/repricing?sector=&regime=`); none is every client. */
class RepricingFilterDTO extends Data
{
    public function __construct(
        public ?string $sector = null,
        public ?string $regime = null,
    ) {}
}
