<?php

namespace App\Modules\Pipeline\DTOs;

use Spatie\LaravelData\Data;

class LeadBoardFilterDTO extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?string $stage = null,
    ) {}
}
