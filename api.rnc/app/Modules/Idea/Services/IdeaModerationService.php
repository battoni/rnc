<?php

namespace App\Modules\Idea\Services;

use App\Modules\Idea\DTOs\IdeaMergeDTO;
use App\Modules\Idea\DTOs\IdeaStatusDTO;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Repositories\IdeaRepository;
use App\Modules\Idea\Repositories\IdeaVoteRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdeaModerationService
{
    private const MERGE_SAME_IDEA = 'ideas.merge.sameIdea';

    private const MERGE_TARGET_UNAVAILABLE = 'ideas.merge.targetUnavailable';

    private const MERGE_ALREADY_MERGED = 'ideas.merge.alreadyMerged';

    public function __construct(
        private IdeaRepository $ideaRepository,
        private IdeaVoteRepository $voteRepository,
    ) {}

    /**
     * Set the status and, with it, the public sentence explaining the status.
     *
     * `delivered_at` is written here and nowhere else, and it is CLEARED when the status leaves
     * `delivered` — Novidades orders by it, so a stale timestamp would keep announcing something
     * that got walked back.
     */
    public function updateStatus(int $id, IdeaStatusDTO $dto): ?Idea
    {
        $idea = $this->ideaRepository->findIdeaForWrite($id);

        if (! $idea) {
            return null;
        }

        $status = IdeaStatus::from($dto->status);

        return $this->ideaRepository->updateIdea($idea, [
            'status' => $status,
            'admin_note' => $dto->adminNote,
            'delivered_at' => $status->isDelivered() ? ($idea->delivered_at ?? now()) : null,
        ]);
    }

    /** Hiding is reversible and keeps the row; deleting someone's words is not moderation. */
    public function toggleHidden(int $id): ?Idea
    {
        $idea = $this->ideaRepository->findIdeaForWrite($id);

        if (! $idea) {
            return null;
        }

        return $this->ideaRepository->updateIdea($idea, [
            'hidden_at' => $idea->isHidden() ? null : now(),
        ]);
    }

    /**
     * Fold a duplicate into the idea it duplicates.
     *
     * The supporters move — that is the whole point, and it is why merging beats deleting: the
     * people who cared about the duplicate keep caring about something that still exists. Anyone
     * who supported both counts once, because the unique index says a person supports an idea,
     * not a number of times.
     *
     * The duplicate is hidden rather than deleted so its author can still be answered, and it
     * points at the canonical row so a stale link lands somewhere real.
     *
     * A missing duplicate is null (404). Every other refusal is a 422 naming its rule: the target
     * must be a DIFFERENT idea that is still on the board — merging into a hidden or already
     * merged row would move the supporters somewhere nobody can see them — and a duplicate is
     * merged once.
     *
     * @throws ValidationException
     */
    public function merge(int $id, IdeaMergeDTO $dto): ?Idea
    {
        $duplicate = $this->ideaRepository->findIdeaForWrite($id);

        if (! $duplicate) {
            return null;
        }

        if ($duplicate->isMerged()) {
            throw ValidationException::withMessages(['id' => [self::MERGE_ALREADY_MERGED]]);
        }

        if ($duplicate->id === $dto->targetId) {
            throw ValidationException::withMessages(['targetId' => [self::MERGE_SAME_IDEA]]);
        }

        $target = $this->ideaRepository->findIdeaForWrite($dto->targetId);

        $isTargetUnavailable = ! $target || $target->isHidden() || $target->isMerged();

        if ($isTargetUnavailable) {
            throw ValidationException::withMessages(['targetId' => [self::MERGE_TARGET_UNAVAILABLE]]);
        }

        return DB::transaction(function () use ($duplicate, $target): Idea {
            $this->voteRepository->transferVotes($duplicate->id, $target->id);
            $this->voteRepository->deleteVotesForIdea($duplicate->id);

            $this->ideaRepository->updateIdea($duplicate, [
                'merged_into_id' => $target->id,
                'hidden_at' => $duplicate->hidden_at ?? now(),
                'votes_count' => 0,
            ]);

            return $this->ideaRepository->recountIdeaVotes($target);
        });
    }
}
