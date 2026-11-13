<?php

namespace App\Modules\Pipeline\DTOs;

use App\Modules\Pipeline\Enums\MoveVia;
use Spatie\LaravelData\Data;

class MoveLeadStageDTO extends Data
{
    public function __construct(
        public string $stage,
        public ?string $reason = null,
        public string $via = MoveVia::Button->value,
    ) {}
}
