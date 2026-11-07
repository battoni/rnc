<?php

namespace App\Modules\Portfolio\DTOs;

use Spatie\LaravelData\Data;

/** The clients chosen for the batch mark (`POST /api/repricing/mark`, R26). */
class MarkRepricingDTO extends Data
{
    /** @param  list<int>  $client_ids */
    public function __construct(
        public array $client_ids,
    ) {}
}
