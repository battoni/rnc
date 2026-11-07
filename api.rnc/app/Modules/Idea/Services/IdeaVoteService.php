<?php

namespace App\Modules\Idea\Services;

use App\Models\User;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Repositories\IdeaRepository;
use App\Modules\Idea\Repositories\IdeaVoteRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class IdeaVoteService
{
    public function __construct(
        private IdeaRepository $ideaRepository,
        private IdeaVoteRepository $voteRepository,
        private IdeaAccessService $accessService,
    ) {}

    /**
     * Support an idea. Idempotent by design: a second click, a double-submit and two tabs all
     * mean the same thing, and the unique index on (idea_id, user_id) is what makes that true
     * even when the two requests race. Catching the violation and returning the current state
     * is the correct answer, not an error the interface would have to explain.
     *
     * The author may support their own idea. Excluding them would make the board's ordering
     * quietly punish the person who wrote the thing.
     */
    public function vote(User $user, int $id): ?Idea
    {
        $idea = $this->readableIdea($user, $id);

        if (! $idea) {
            return null;
        }

        if ($this->voteRepository->findVote($idea->id, $user->id)) {
            return $this->currentState($idea, $user, true);
        }

        try {
            DB::transaction(function () use ($idea, $user): void {
                $this->voteRepository->createVote($idea->id, $user->id);
                $idea->increment('votes_count');
            });
        } catch (UniqueConstraintViolationException) {
            /*
             * Two requests raced past the check above and the index settled it. Narrow on
             * purpose: any other QueryException is a real fault and must not be reported to the
             * client as a successful vote.
             */
        }

        return $this->currentState($idea, $user, true);
    }

    public function unvote(User $user, int $id): ?Idea
    {
        $idea = $this->readableIdea($user, $id);

        if (! $idea) {
            return null;
        }

        $vote = $this->voteRepository->findVote($idea->id, $user->id);

        if (! $vote) {
            return $this->currentState($idea, $user, false);
        }

        DB::transaction(function () use ($idea, $vote): void {
            $this->voteRepository->deleteVote($vote);
            $idea->decrement('votes_count');
        });

        return $this->currentState($idea, $user, false);
    }

    /**
     * A vote on a hidden idea is a vote on something the viewer is not supposed to know exists,
     * and a vote on a merged duplicate lands on a row nobody reads. Both are 404 rather than a
     * silent no-op, so the client never shows a count that will not survive a reload.
     */
    private function readableIdea(User $user, int $id): ?Idea
    {
        $idea = $this->ideaRepository->findIdeaForWrite($id);

        if (! $idea || $idea->isMerged()) {
            return null;
        }

        $isVisible = ! $idea->isHidden() || $this->accessService->canModerate($user);

        return $isVisible ? $idea : null;
    }

    /** The counter is authoritative only after a re-read; the increment happened in the DB. */
    private function currentState(Idea $idea, User $user, bool $voted): Idea
    {
        $fresh = $this->ideaRepository->recountIdeaVotes($idea);
        $fresh->setAttribute('voted', $voted);

        return $fresh;
    }
}
