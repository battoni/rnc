<?php

namespace App\Modules\Metrics\DTOs;

use Spatie\LaravelData\Data;

class FunnelWindowDTO extends Data
{
    public function __construct(
        public ?int $days = null,
    ) {}
}
