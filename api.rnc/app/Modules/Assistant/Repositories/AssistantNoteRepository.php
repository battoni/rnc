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

    /** "N notas vencem em 7 dias": served notes 83–90 days old (AM19), one count query. */
    public function countExpiringNotes(CarbonImmutable $now): int
    {
        return AssistantNote::query()->expiringSoon($now)->count();
    }

    /**
     * The notes whose reviewer has not yet heard that this review runs out — expiring soon, and no
     * bell since the review (a bell before a newer review is stale).
     *
     * @return Collection<int, AssistantNote> with the reviewer
     */
    public function findNotesToWarn(CarbonImmutable $now): Collection
    {
        return AssistantNote::query()
            ->expiringSoon($now)
            ->whereNotNull('reviewer_id')
            ->where(fn ($query) => $query->whereNull('expiry_notified_at')->orWhereColumn('expiry_notified_at', '<', 'reviewed_at'))
            ->with('reviewer')
            ->orderBy('id')
            ->get();
    }

    /**
     * Mark the review's bell as rung, unless another run already did: true when this call claimed it
     * (one conditional update, so two overlapping runs never ring twice).
     */
    public function claimExpiryNotice(AssistantNote $note, CarbonImmutable $now): bool
    {
        return AssistantNote::query()
            ->whereKey($note->id)
            ->where(fn ($query) => $query->whereNull('expiry_notified_at')->orWhereColumn('expiry_notified_at', '<', 'reviewed_at'))
            ->update(['expiry_notified_at' => $now->utc()->format('Y-m-d H:i:s')]) === 1;
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
