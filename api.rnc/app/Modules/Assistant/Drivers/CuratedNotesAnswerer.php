<?php

namespace App\Modules\Assistant\Drivers;

use App\Modules\Assistant\Contracts\ReformaAnswerer;
use App\Modules\Assistant\Helpers\NoteMatcher;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Repositories\AssistantNoteRepository;
use Carbon\CarbonImmutable;

/**
 * The `curated` driver (rnc-3 R12, D9, W9): the demo's matcher over the current firm's served notes —
 * revisada and reviewed within 90 days. Deterministic, local, nothing generated: a question it cannot
 * match to a note answers null.
 */
class CuratedNotesAnswerer implements ReformaAnswerer
{
    public function __construct(private AssistantNoteRepository $noteRepository) {}

    public function answer(string $question): ?AssistantNote
    {
        $now = CarbonImmutable::now();
        $noteId = NoteMatcher::best($question, $this->noteRepository->findServedNoteKeywords($now));

        return $noteId === null ? null : $this->noteRepository->findServedNoteById($noteId, $now);
    }

    public function answerById(int $noteId): ?AssistantNote
    {
        return $this->noteRepository->findServedNoteById($noteId, CarbonImmutable::now());
    }
}
