<?php

namespace App\Modules\Idea\Repositories;

use App\Modules\Idea\Models\IdeaVote;

class IdeaVoteRepository
{
    public function __construct(
        private IdeaVote $model
    ) {}

    public function findVote(int $ideaId, int $userId): ?IdeaVote
    {
        return $this->model->where('idea_id', $ideaId)->where('user_id', $userId)->first();
    }

    public function createVote(int $ideaId, int $userId): IdeaVote
    {
        return $this->model->create(['idea_id' => $ideaId, 'user_id' => $userId]);
    }

    public function deleteVote(IdeaVote $vote): void
    {
        $vote->delete();
    }

    /**
     * Move a duplicate's supporters onto the canonical idea, skipping anyone who already
     * supported both — the unique index would reject those, and "already supported" is the
     * correct outcome rather than an error. Returns how many actually moved.
     */
    public function transferVotes(int $fromIdeaId, int $toIdeaId): int
    {
        $existingUserIds = $this->model->where('idea_id', $toIdeaId)->pluck('user_id')->all();

        return $this->model
            ->where('idea_id', $fromIdeaId)
            ->whereNotIn('user_id', $existingUserIds)
            ->update(['idea_id' => $toIdeaId]);
    }

    public function deleteVotesForIdea(int $ideaId): void
    {
        $this->model->where('idea_id', $ideaId)->delete();
    }
}
