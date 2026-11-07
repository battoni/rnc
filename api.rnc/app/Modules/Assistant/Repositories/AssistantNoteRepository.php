<?php

namespace App\Modules\Assistant\Repositories;

use App\Modules\Assistant\Models\AssistantNote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** The current firm's notes. `BelongsToFirm` scopes and stamps the firm: another firm's note is absent. */
class AssistantNoteRepository
{
    private const SUGGESTION_LIMIT = 12;

    /** @return Collection<int, AssistantNote> every note, by id, with its reviewer and last editor (three queries) */
    public function findAllNotes(): Collection
    {
        return AssistantNote::query()->with(['reviewer', 'lastEditor'])->orderBy('id')->get();
    }

    public function findNoteById(int $id): ?AssistantNote
    {
        return AssistantNote::query()->find($id);
    }

    public function findServedNoteById(int $id, CarbonImmutable $now): ?AssistantNote
    {
        return AssistantNote::query()->served($now)->with('reviewer')->find($id);
    }

    /**
     * What the matcher needs of each served note, in one query.
     *
     * @return list<array{id: int, strong: list<string>, weak: list<string>, reviewed_at: int}>
     */
    public function findServedNoteKeywords(CarbonImmutable $now): array
    {
        return AssistantNote::query()
            ->served($now)
            ->get(['id', 'keywords_strong', 'keywords_weak', 'reviewed_at'])
            ->map(fn (AssistantNote $note) => [
                'id' => $note->id,
                'strong' => $note->keywords_strong,
                'weak' => $note->keywords_weak,
                'reviewed_at' => (int) $note->reviewed_at?->getTimestamp(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, AssistantNote> the chips: served notes' ids and questions, by id */
    public function findServedNoteQuestions(CarbonImmutable $now): Collection
    {
        return AssistantNote::query()->served($now)->orderBy('id')->limit(self::SUGGESTION_LIMIT)->get(['id', 'question']);
    }

    public function slugExists(string $slug): bool
    {
        return AssistantNote::query()->where('slug', $slug)->exists();
    }

    /** @param  array<string, mixed>  $attributes */
    public function createNote(array $attributes): AssistantNote
    {
        return AssistantNote::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateNote(AssistantNote $note, array $attributes): AssistantNote
    {
        $note->update($attributes);

        return $note;
    }
}
