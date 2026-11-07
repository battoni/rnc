<?php

namespace App\Modules\Idea\Services;

use App\Models\User;
use App\Modules\Idea\DTOs\IdeaListDTO;
use App\Modules\Idea\DTOs\IdeaStoreDTO;
use App\Modules\Idea\DTOs\IdeaUpdateDTO;
use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Repositories\IdeaRepository;
use Illuminate\Support\Facades\DB;

class IdeaService
{
    public function __construct(
        private IdeaRepository $ideaRepository,
        private IdeaAccessService $accessService,
        private IdeaAttachmentService $attachmentService,
    ) {}

    /** @return Idea[] */
    public function list(User $user, IdeaListDTO $filters): array
    {
        return $this->ideaRepository->findIdeasForBoard(
            $filters,
            $user->id,
            $this->accessService->canModerate($user)
        );
    }

    public function show(User $user, int $id): ?Idea
    {
        return $this->ideaRepository->findIdeaById($id, $user->id, $this->accessService->canModerate($user));
    }

    /**
     * The row and its image are one write: a failure storing the attachment must not leave an
     * idea behind while the API answers 500. The blob written before a rollback is removed by
     * `ImageAttachmentService`.
     */
    public function create(User $user, IdeaStoreDTO $dto): Idea
    {
        $idea = DB::transaction(function () use ($user, $dto): Idea {
            $idea = $this->ideaRepository->createIdea([
                'user_id' => $user->id,
                'title' => $dto->title,
                'description' => $dto->description,
                'type' => $dto->type,
                'area' => $dto->area ?? IdeaArea::GENERAL->value,
            ]);

            if ($dto->attachment) {
                $this->attachmentService->replaceAttachment($idea, $dto->attachment);
            }

            return $idea;
        });

        return $this->decorateForViewer($this->ideaRepository->reloadIdea($idea), $user);
    }

    /**
     * An idea the person may WRITE is first an idea they may SEE: a hidden or missing row is
     * "not found" for a write exactly as for `show`, its own author included. Answering 403
     * instead would confirm that the hidden row exists.
     */
    public function findWritable(User $user, int $id): ?Idea
    {
        return $this->ideaRepository->findIdeaForWrite($id, $this->accessService->canModerate($user));
    }

    /**
     * The author may rewrite their own idea only while it is still `received`.
     *
     * Once it has a status or a supporter, the text is no longer only theirs: the maintainer's
     * public note answers these words, and the people who supported it supported these words.
     * A moderator is exempt — fixing a typo in someone's title is part of running the board.
     */
    public function update(User $user, Idea $idea, IdeaUpdateDTO $dto): ?Idea
    {
        if (! $this->canWrite($user, $idea)) {
            return null;
        }

        $updated = DB::transaction(function () use ($idea, $dto): Idea {
            $updated = $this->ideaRepository->updateIdea($idea, [
                'title' => $dto->title,
                'description' => $dto->description,
                'type' => $dto->type,
                'area' => $dto->area ?? IdeaArea::GENERAL->value,
            ]);

            if ($dto->removeAttachment) {
                $this->attachmentService->deleteAttachment($updated);
            }

            if ($dto->attachment) {
                $this->attachmentService->replaceAttachment($updated, $dto->attachment);
            }

            return $updated;
        });

        return $this->decorateForViewer($this->ideaRepository->reloadIdea($updated), $user);
    }

    public function delete(User $user, Idea $idea): ?Idea
    {
        if (! $this->canWrite($user, $idea)) {
            return null;
        }

        DB::transaction(function () use ($idea): void {
            $this->attachmentService->deleteAttachment($idea);
            $this->ideaRepository->deleteIdea($idea);
        });

        return $idea;
    }

    /** The rule itself lives on the model, so the endpoint and the client's button share it. */
    private function canWrite(User $user, Idea $idea): bool
    {
        return $idea->isEditableBy($user->id, $this->accessService->canModerate($user));
    }

    /**
     * `voted` rides on the board query as a computed column; a freshly written row never went
     * through that query, so the flag has to be stated rather than left undefined — the client
     * reads it on every card.
     */
    private function decorateForViewer(Idea $idea, User $user): Idea
    {
        $idea->setAttribute('voted', $idea->votes()->where('user_id', $user->id)->exists());

        return $idea;
    }
}
