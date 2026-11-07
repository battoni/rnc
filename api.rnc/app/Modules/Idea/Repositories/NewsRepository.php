<?php

namespace App\Modules\Idea\Repositories;

use App\Models\User;
use App\Modules\Idea\Enums\NewsReactionType;
use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsReaction;
use App\Modules\Idea\Models\NewsRead;
use Illuminate\Support\Facades\DB;

class NewsRepository
{
    public function __construct(
        private News $model
    ) {}

    /**
     * Drafts are the maintainer's alone. A changelog the reader watches assemble itself is a
     * changelog that announces work before it is worth announcing.
     *
     * @return News[]
     */
    public function findNewsEntries(bool $includeDrafts): array
    {
        return $this->model
            ->when(! $includeDrafts, fn ($query) => $query->where('is_published', true))
            ->with(['idea.user', 'user', 'cover', 'areas'])
            /*
             * Sort by the date the SCREEN shows, which for a draft is when it was written.
             *
             * Ordering by `published_at` alone puts NULLs last in MySQL, while the list groups by
             * `publishedAt ?? createdAt` — so a draft written this month landed in a September
             * group rendered BELOW August. Newest-at-the-bottom, in a changelog.
             */
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->latest('created_at')
            ->get()
            ->all();
    }

    /**
     * The three aggregates every entry carries, fetched for the WHOLE list in three queries.
     *
     * The obvious implementation eager-loads the `reactions` and `reads` relations and counts them
     * in PHP, which is one query per relation but drags every row of both tables across the wire —
     * users x 3 reaction rows and one read row per person per entry. These are grouped counts
     * instead: the database does the counting, and the payload is proportional to the number of
     * entries rather than to the size of the audience.
     */
    public function attachNewsAggregates(array $entries, User $user, bool $includeReadCount = false): array
    {
        if ($entries === []) {
            return $entries;
        }

        $ids = array_map(fn (News $entry) => $entry->id, $entries);

        $totals = NewsReaction::whereIn('news_id', $ids)
            ->groupBy('news_id', 'type')
            ->select('news_id', 'type', DB::raw('SUM(count) as total'))
            ->get()
            ->groupBy('news_id');

        $mine = NewsReaction::whereIn('news_id', $ids)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (NewsReaction $reaction) => $reaction->news_id.':'.$reaction->type->value);

        $reads = NewsRead::whereIn('news_id', $ids)
            ->groupBy('news_id')
            ->select('news_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN user_id = '.((int) $user->id).' THEN 1 ELSE 0 END) as mine'))
            ->get()
            ->keyBy('news_id');

        foreach ($entries as $entry) {
            $entryTotals = $totals->get($entry->id) ?? collect();

            $entry->reaction_totals = collect(NewsReactionType::values())
                ->mapWithKeys(fn (string $type) => [
                    $type => (int) ($entryTotals->firstWhere('type', $type)->total ?? 0),
                ])
                ->all();

            $entry->reaction_mine = collect(NewsReactionType::values())
                ->mapWithKeys(fn (string $type) => [
                    $type => (int) ($mine->get($entry->id.':'.$type)->count ?? 0),
                ])
                ->all();

            if ($includeReadCount) {
                $entry->read_count = (int) ($reads->get($entry->id)->total ?? 0);
            }

            $entry->is_read_by_user = ((int) ($reads->get($entry->id)->mine ?? 0)) > 0;
        }

        return $entries;
    }

    /** @return NewsRead[] */
    public function findNewsReaders(int $newsId): array
    {
        return NewsRead::where('news_id', $newsId)
            ->with('user')
            ->orderByDesc('read_at')
            ->get()
            ->all();
    }

    /**
     * Recorded once and never updated — see the migration. `firstOrCreate` rather than `updateOr`
     * is the whole rule: opening an entry a second time must not overwrite the answer to "did
     * anyone read this when I published it".
     *
     * Two tabs racing past the lookup are safe: `firstOrCreate` inserts through `createOrFirst`,
     * which catches the unique-index violation (inside a savepoint) and reads the winner back.
     * `NewsEngagementRaceTest` holds that promise.
     */
    public function recordNewsRead(int $newsId, int $userId): void
    {
        NewsRead::firstOrCreate(
            ['news_id' => $newsId, 'user_id' => $userId],
            ['read_at' => now()]
        );
    }

    /**
     * The increment is a single SQL statement, not a read-then-write. Applause arrives in bursts
     * from one impatient thumb, and two clicks racing through a read-modify-write would land as
     * one. `firstOrCreate` seeds the row at zero; two first clicks racing past its lookup are safe
     * because it inserts through `createOrFirst`, which catches the unique-index violation and
     * reads the winner back (`NewsEngagementRaceTest`).
     *
     * Written as two portable statements rather than one `upsert`, because an upsert's
     * "increment the existing value" clause is spelled differently on every engine — `excluded.`
     * on SQLite and Postgres, `VALUES()` on MySQL — and this repository should not care which one
     * is behind it.
     */
    public function incrementNewsReaction(int $newsId, int $userId, NewsReactionType $type): void
    {
        $keys = ['news_id' => $newsId, 'user_id' => $userId, 'type' => $type->value];

        NewsReaction::firstOrCreate($keys, ['count' => 0]);

        NewsReaction::where($keys)->increment('count');
    }

    /** How many published entries this person has not opened — the nav dot, and nothing else. */
    public function countUnreadNews(int $userId): int
    {
        return $this->model
            ->where('is_published', true)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $userId))
            ->count();
    }

    public function findNewsById(int $id): ?News
    {
        return $this->model->with(['idea.user', 'user', 'cover', 'areas'])->find($id);
    }

    public function createNews(array $data): News
    {
        return $this->model->create($data)->load(['idea.user', 'user', 'cover']);
    }

    public function updateNews(News $news, array $data): News
    {
        $news->update($data);

        return $news->fresh(['idea.user', 'user', 'cover']);
    }

    public function deleteNews(News $news): void
    {
        $news->delete();
    }
}
