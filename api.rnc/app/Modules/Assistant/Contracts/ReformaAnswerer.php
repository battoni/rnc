<?php

namespace App\Modules\Assistant\Contracts;

use App\Modules\Assistant\Models\AssistantNote;

/**
 * Who answers a Reforma question (rnc-3 R12, R16, D9). Bound by `config('assistant.driver')` in
 * `AppServiceProvider::ASSISTANT_DRIVERS`; the only driver is `curated`. Whatever the driver, the
 * answer is one of the current firm's served notes or null — a driver never writes text of its own.
 */
interface ReformaAnswerer
{
    /** The best served note for the question, or null: the question is then handed to a specialist. */
    public function answer(string $question): ?AssistantNote;

    /** A suggestion chip's note, if it is still served; null otherwise. */
    public function answerById(int $noteId): ?AssistantNote;
}
