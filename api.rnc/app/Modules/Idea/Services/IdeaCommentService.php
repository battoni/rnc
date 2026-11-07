<?php

namespace App\Modules\Idea\Services;

use App\Models\User;
use App\Modules\Idea\DTOs\IdeaCommentStoreDTO;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaComment;
use App\Modules\Idea\Repositories\IdeaCommentRepository;
use App\Modules\Idea\Repositories\IdeaRepository;
use Illuminate\Support\Facades\DB;

class IdeaCommentService
{
    public function __construct(
        private IdeaRepository $ideaRepository,
        private IdeaCommentRepository $commentRepository,
        private IdeaAccessService $accessService,
    ) {}

    /** @return IdeaComment[]|null */
    public function list(User $user, int $ideaId): ?array
    {
        $idea = $this->readableIdea($user, $ideaId);

        if (! $idea) {
            return null;
        }

        return $this->commentRepository->findCommentsForIdea($ideaId, $this->accessService->canModerate($user));
    }

    /**
     * `is_official` is stamped at write time from the author's permission, not read from their
     * role at render time. Someone who answers as the maintainer today and loses the permission
     * tomorrow still answered as the maintainer, and the thread should keep saying so.
     */
    public function create(User $user, int $ideaId, IdeaCommentStoreDTO $dto): ?IdeaComment
    {
        $idea = $this->readableIdea($user, $ideaId);

        if (! $idea) {
            return null;
        }

        return DB::transaction(function () use ($idea, $user, $dto): IdeaComment {
            $comment = $this->commentRepository->createComment([
                'idea_id' => $idea->id,
                'user_id' => $user->id,
                'body' => $dto->body,
                'is_official' => $this->accessService->canModerate($user),
            ]);

            $this->syncCommentCount($idea);

            return $comment;
        });
    }

    /**
     * A moderator hides; an author deletes their own. The two are different gestures with
     * different traces, and collapsing them would let a moderator quietly erase criticism.
     */
    public function delete(User $user, int $commentId): ?IdeaComment
    {
        $comment = $this->commentRepository->findCommentById($commentId);

        if (! $comment) {
            return null;
        }

        $canModerate = $this->accessService->canModerate($user);
        $isOwnComment = $comment->user_id === $user->id;

        if (! $canModerate && ! $isOwnComment) {
            return null;
        }

        /*
         * Whose comment it is, and whether it is already hidden, decide which gesture happens —
         * never who is asking.
         *
         * A moderator acting on someone else's VISIBLE comment hides it: the row survives, the
         * author can still be answered, and the decision is reversible in the sense that matters.
         * Acting on the same comment AGAIN removes it for good — otherwise hiding is the only
         * thing that can ever happen to spam, and the board accumulates it forever.
         *
         * Anyone deleting their OWN comment deletes it, moderator included: taking back what you
         * said is not moderation and should leave no moderated trace.
         */
        $shouldHide = $canModerate && ! $isOwnComment && ! $comment->isHidden();

        return DB::transaction(function () use ($comment, $shouldHide): IdeaComment {
            if ($shouldHide) {
                $comment->update(['hidden_at' => now()]);
            }

            if (! $shouldHide) {
                $this->commentRepository->deleteComment($comment);
            }

            $this->syncCommentCount($comment->idea);

            return $comment;
        });
    }

    private function readableIdea(User $user, int $ideaId): ?Idea
    {
        return $this->ideaRepository->findIdeaById($ideaId, $user->id, $this->accessService->canModerate($user));
    }

    /** The counter tracks what a reader can see, so a hidden comment must not inflate it. */
    private function syncCommentCount(?Idea $idea): void
    {
        if (! $idea) {
            return;
        }

        $idea->update(['comments_count' => $this->commentRepository->countVisibleCommentsForIdea($idea->id)]);
    }
}
