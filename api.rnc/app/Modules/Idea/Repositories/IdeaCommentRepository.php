<?php

namespace App\Modules\Idea\Repositories;

use App\Modules\Idea\Models\IdeaComment;

class IdeaCommentRepository
{
    public function __construct(
        private IdeaComment $model
    ) {}

    /** @return IdeaComment[] */
    public function findCommentsForIdea(int $ideaId, bool $canModerate): array
    {
        return $this->model
            ->where('idea_id', $ideaId)
            ->when(! $canModerate, fn ($query) => $query->whereNull('hidden_at'))
            ->with('user')
            ->oldest('created_at')
            ->get()
            ->all();
    }

    public function findCommentById(int $id): ?IdeaComment
    {
        return $this->model->with(['user', 'idea'])->find($id);
    }

    public function createComment(array $data): IdeaComment
    {
        return $this->model->create($data)->load('user');
    }

    public function deleteComment(IdeaComment $comment): void
    {
        $comment->delete();
    }

    public function countVisibleCommentsForIdea(int $ideaId): int
    {
        return $this->model->where('idea_id', $ideaId)->whereNull('hidden_at')->count();
    }
}
