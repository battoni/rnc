<?php

namespace App\Modules\Idea\Repositories;

use App\Modules\Idea\DTOs\IdeaListDTO;
use App\Modules\Idea\Models\Idea;
use Illuminate\Database\Eloquent\Builder;

class IdeaRepository
{
    /**
     * The board returns every matching row rather than a page. A feedback board for one product
     * is small — the filters narrow it further — and infinite scroll on a list people read top
     * to bottom buys nothing. The cap is here so that assumption failing costs a truncated list
     * instead of a timeout; revisit paging when a real board approaches it.
     */
    private const BOARD_LIMIT = 200;

    public function __construct(
        private Idea $model
    ) {}

    /** @return Idea[] */
    public function findIdeasForBoard(IdeaListDTO $filters, int $viewerId, bool $canModerate): array
    {
        return $this->boardQuery($viewerId, $canModerate)
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->type, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters->area, fn (Builder $query, string $area) => $query->where('area', $area))
            ->when($filters->mine, fn (Builder $query) => $query->where('user_id', $viewerId))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when(
                $filters->sort === 'recent',
                fn (Builder $query) => $query->latest('created_at'),
                fn (Builder $query) => $query->orderByDesc('votes_count')->latest('created_at')
            )
            ->limit(self::BOARD_LIMIT)
            ->get()
            ->all();
    }

    /**
     * A hidden idea is invisible to everyone but a moderator — including its own author. Letting
     * the author still read it would make hiding a conversation between two people instead of a
     * moderation decision, and the 404 is the same answer a deleted row would give.
     *
     * The comments come back under the SAME visibility rule as `GET ideas/{id}/comments`, and that
     * is not a detail. The detail screen reads its thread from here, so filtering hidden comments
     * unconditionally left a moderator unable to see the comment they had just hidden — and
     * therefore unable to act on it again.
     */
    public function findIdeaById(int $id, int $viewerId, bool $canModerate): ?Idea
    {
        return $this->boardQuery($viewerId, $canModerate)
            ->with(['comments' => fn ($query) => $query
                ->when(! $canModerate, fn ($inner) => $inner->whereNull('hidden_at'))
                ->with('user')
                ->oldest('created_at')])
            ->find($id);
    }

    /**
     * Without the viewer-scoped extras — for writes, which do their own authorisation. A hidden
     * row is left out unless the caller moderates, the same rule `findIdeaById` applies.
     */
    public function findIdeaForWrite(int $id, bool $canModerate = true): ?Idea
    {
        return $this->model
            ->newQuery()
            ->with(['user', 'attachment'])
            ->when(! $canModerate, fn (Builder $query) => $query->whereNull('hidden_at'))
            ->find($id);
    }

    /** The row as it is now, with what a resource reads. */
    public function reloadIdea(Idea $idea): Idea
    {
        return $idea->fresh(['user', 'attachment']) ?? $idea;
    }

    public function createIdea(array $data): Idea
    {
        return $this->model->create($data);
    }

    public function updateIdea(Idea $idea, array $data): Idea
    {
        $idea->update($data);

        return $idea->fresh(['user', 'attachment']);
    }

    public function deleteIdea(Idea $idea): void
    {
        $idea->delete();
    }

    /** @return Idea[] */
    public function findIdeasMergedInto(int $ideaId): array
    {
        return $this->model->where('merged_into_id', $ideaId)->get()->all();
    }

    public function recountIdeaVotes(Idea $idea): Idea
    {
        $idea->update(['votes_count' => $idea->votes()->count()]);

        return $idea->fresh(['user', 'attachment']);
    }

    /**
     * `voted` and the eager loads are what keep the board one query instead of one per row: the
     * card needs the author's name and whether this viewer already supported it.
     *
     * @return Builder<Idea>
     */
    private function boardQuery(int $viewerId, bool $canModerate): Builder
    {
        return $this->model
            ->newQuery()
            ->with(['user', 'attachment'])
            ->withExists(['votes as voted' => fn ($query) => $query->where('user_id', $viewerId)])
            ->when(! $canModerate, fn (Builder $query) => $query->whereNull('hidden_at'));
    }
}
