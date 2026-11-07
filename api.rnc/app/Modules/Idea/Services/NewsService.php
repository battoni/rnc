<?php

namespace App\Modules\Idea\Services;

use App\Models\User;
use App\Modules\Idea\DTOs\NewsStoreDTO;
use App\Modules\Idea\DTOs\NewsUpdateDTO;
use App\Modules\Idea\Enums\NewsReactionType;
use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsRead;
use App\Modules\Idea\Repositories\NewsRepository;
use Illuminate\Support\Facades\DB;

class NewsService
{
    private const MANAGE_PERMISSION = 'news.manage';

    public function __construct(
        private NewsRepository $newsRepository,
        private NewsBodySanitizer $sanitizer,
        private NewsCoverService $covers,
        private IdeaAccessService $accessService,
    ) {}

    /**
     * Drafts are the writer's alone. A changelog the reader watches assemble itself is a changelog
     * that announces work before it is worth announcing.
     *
     * @return News[]
     */
    public function list(User $user): array
    {
        $entries = $this->newsRepository->findNewsEntries($this->canManage($user));

        return $this->newsRepository->attachNewsAggregates($entries, $user, $this->canManage($user));
    }

    /**
     * Opening the article is what marks it read. Not scrolling past the card, not landing on the
     * page — a dot that clears itself because you walked past it is a dot that stops meaning
     * anything, and the writer's read count would count glances.
     *
     * A draft is never marked read: the only person who can open one is its writer, and the writer
     * reading their own draft is not an audience.
     */
    public function markRead(User $user, int $id): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news || ! $news->is_published) {
            return null;
        }

        $this->newsRepository->recordNewsRead($news->id, $user->id);

        return $this->withAggregates($news, $user);
    }

    /**
     * Who read it. The writer's alone — a changelog that told every reader who else had read it
     * would be publishing the audience's behaviour to the audience.
     *
     * Closed by `permission:news.manage` on the route, like every other write in this module.
     *
     * @return NewsRead[]
     */
    public function readers(int $id): ?array
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news) {
            return null;
        }

        return $this->newsRepository->findNewsReaders($news->id);
    }

    /** Applause on a published entry. Unlimited, on purpose — see the reactions migration. */
    public function react(User $user, int $id, NewsReactionType $type): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news || ! $news->is_published) {
            return null;
        }

        $this->newsRepository->incrementNewsReaction($news->id, $user->id, $type);

        return $this->withAggregates($news, $user);
    }

    /**
     * The entry a share link may show. A draft is not shareable, and the refusal is a 404 rather
     * than a 403: telling a stranger "this exists but is not published" is itself a leak of the
     * writer's work in progress. The cover comes loaded — the share page's head needs it.
     */
    public function findShareable(int $id): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        return $news && $news->is_published ? $news : null;
    }

    /** The nav dot, and nothing else: how many published entries this person has not opened. */
    public function unreadCount(User $user): int
    {
        return $this->newsRepository->countUnreadNews($user->id);
    }

    /**
     * The entry, its areas and its cover are one write. A cover that fails to store must not
     * leave an entry without it while the API answers 500; the blob written before a rollback is
     * removed by `ImageAttachmentService`.
     */
    public function create(User $user, NewsStoreDTO $dto): News
    {
        $news = DB::transaction(function () use ($user, $dto): News {
            $news = $this->newsRepository->createNews([
                'title' => $dto->title,
                'subtitle' => $dto->subtitle,
                // Sanitised on the way IN. What is stored is what has already been made safe — see
                // NewsBodySanitizer for why that is not the same as cleaning it at render time.
                'body' => $this->sanitizer->sanitize($dto->body),
                'link' => $dto->link,
                'idea_id' => $dto->ideaId,
                'user_id' => $user->id,
                'published_at' => $dto->publish ? now() : null,
                'is_published' => (bool) $dto->publish,
            ]);

            $this->syncAreas($news, $dto->areas);

            if ($dto->cover) {
                $this->covers->replaceCover($news, $dto->cover);
            }

            return $news;
        });

        return $this->withAggregates($news, $user);
    }

    /**
     * Publishing is a one-way door within an edit: an entry already out keeps the timestamp it
     * went out with, because a changelog that silently re-dates itself is not a record. Pulling it
     * back to a draft is the explicit `publish: false`.
     */
    public function update(User $user, int $id, NewsUpdateDTO $dto): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news) {
            return null;
        }

        // One write: `syncAreas` deletes before it inserts, and a failure between the two must
        // not leave the entry with no areas.
        $updated = DB::transaction(function () use ($news, $dto): News {
            $updated = $this->newsRepository->updateNews($news, [
                'title' => $dto->title,
                'subtitle' => $dto->subtitle,
                'body' => $this->sanitizer->sanitize($dto->body),
                'link' => $dto->link,
                'idea_id' => $dto->ideaId,
                'published_at' => $dto->publish ? ($news->published_at ?? now()) : $news->published_at,
                'is_published' => (bool) $dto->publish,
            ]);

            if ($dto->removeCover) {
                $this->covers->deleteCover($updated);
            }

            if ($dto->cover) {
                $this->covers->replaceCover($updated, $dto->cover);
            }

            $this->syncAreas($updated, $dto->clearAreas ? [] : $dto->areas);

            return $updated;
        });

        return $this->withAggregates($updated, $user);
    }

    /**
     * Replace the entry's areas with the ones given, and leave them alone when none were sent.
     *
     * `null` means “this request is not about areas” — a publish toggle or a cover swap must not
     * strip them. An empty array is a real answer: the entry belongs to no particular part of the
     * product, and that is allowed to be said.
     *
     * A multipart caller cannot write that empty array, and the SPA is always multipart because
     * the entry may carry a cover. `clearAreas` is how it says the empty answer out loud; the
     * update path turns it back into `[]` before it gets here, so this method keeps one rule.
     *
     * @param  array<int, string>|null  $areas
     */
    private function syncAreas(News $news, ?array $areas): void
    {
        if ($areas === null) {
            return;
        }

        $news->areas()->delete();

        foreach (array_unique($areas) as $area) {
            $news->areas()->create(['area' => $area]);
        }
    }

    /**
     * Flip the entry between draft and published, touching nothing else.
     *
     * Same one-way-door rule as `update`: an entry that is already out keeps the date it went out
     * with. Re-publishing something you pulled back does NOT re-date it, because the changelog is
     * a record of when things happened, not of when they were last edited.
     */
    public function setPublished(User $user, int $id, bool $publish): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news) {
            return null;
        }

        $updated = $this->newsRepository->updateNews($news, [
            'published_at' => $publish ? ($news->published_at ?? now()) : $news->published_at,
            'is_published' => $publish,
        ]);

        return $this->withAggregates($updated, $user);
    }

    /**
     * The entry, dressed the way the list dresses one — applause, this reader's own share of it,
     * whether they have read it and (for a maintainer) how many have.
     *
     * EVERY endpoint that answers with an entry goes through here, and that is the rule this
     * method exists to keep. `NewsResource` publishes those four fields under `when(isset(...))`,
     * so a bare model does not send them wrong — it does not send them AT ALL, and the client
     * reads `undefined`. A freshly created entry came back that way and the read filter compared
     * `undefined === false`: the new entry appeared under neither "unread" nor "read", and only a
     * full page reload put it where it belonged.
     */
    private function withAggregates(News $news, User $user): News
    {
        return $this->newsRepository->attachNewsAggregates(
            [$news->fresh(['idea.user', 'user', 'cover', 'areas'])],
            $user,
            $this->canManage($user)
        )[0];
    }

    public function delete(int $id): ?News
    {
        $news = $this->newsRepository->findNewsById($id);

        if (! $news) {
            return null;
        }

        // The file goes with the row; the cascade would take the record and leave the blob.
        DB::transaction(function () use ($news): void {
            $this->covers->deleteCover($news);
            $this->newsRepository->deleteNews($news);
        });

        return $news;
    }

    /** Writing the changelog is its own key now — it used to ride on `ideas.manage`. */
    private function canManage(User $user): bool
    {
        return $this->accessService->can($user, self::MANAGE_PERMISSION);
    }
}
