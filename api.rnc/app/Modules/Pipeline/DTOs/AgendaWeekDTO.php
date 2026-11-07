<?php

namespace App\Modules\Pipeline\DTOs;

use Spatie\LaravelData\Data;

class AgendaWeekDTO extends Data
{
    public function __construct(
        public ?string $week = null,
    ) {}
}
