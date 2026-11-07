<?php

namespace App\Modules\Pipeline\DTOs;

use Spatie\LaravelData\Data;

class LeadNoteDTO extends Data
{
    public function __construct(
        public string $text,
    ) {}
}
