<?php

namespace App\Modules\Assistant\DTOs;

use Spatie\LaravelData\Data;

/** A question to the assistant (`POST /api/assistant/ask`); a chip also names its note (R12). */
class AskDTO extends Data
{
    public function __construct(
        public string $question,
        public ?int $note_id = null,
    ) {}
}
