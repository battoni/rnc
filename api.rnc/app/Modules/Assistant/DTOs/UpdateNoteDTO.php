<?php

namespace App\Modules\Assistant\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * An edit (`PATCH /api/assistant/notes/{id}`): only what the request sent (`toArray()` leaves the
 * rest out). Every field is content, so changing any sends a reviewed note back to rascunho (D7).
 */
class UpdateNoteDTO extends Data
{
    /**
     * @param  list<string>|Optional  $keywords_strong
     * @param  list<string>|Optional  $keywords_weak
     */
    public function __construct(
        public string|Optional $question,
        public string|Optional $answer,
        public string|Optional $rule,
        public string|Optional $source_label,
        public string|Optional $source_url,
        public string|Optional $as_of,
        public array|Optional $keywords_strong,
        public array|Optional $keywords_weak,
    ) {}
}
