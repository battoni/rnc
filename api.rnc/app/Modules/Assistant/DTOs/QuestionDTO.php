<?php

namespace App\Modules\Assistant\DTOs;

use Spatie\LaravelData\Data;

/** A hand-off (`POST /api/assistant/questions`); no specialist named goes to the first by name (R13). */
class QuestionDTO extends Data
{
    public function __construct(
        public string $question,
        public ?int $specialist_id = null,
    ) {}
}
