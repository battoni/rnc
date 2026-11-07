<?php

namespace App\Modules\Assistant\DTOs;

use Spatie\LaravelData\Data;

/** A new note (`POST /api/assistant/notes`), born rascunho (R10). */
class NoteDTO extends Data
{
    /**
     * @param  list<string>  $keywords_strong
     * @param  list<string>  $keywords_weak
     */
    public function __construct(
        public string $question,
        public string $answer,
        public string $rule,
        public string $source_label,
        public string $source_url,
        public string $as_of,
        public array $keywords_strong,
        public array $keywords_weak = [],
    ) {}
}
