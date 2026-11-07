<?php

namespace App\Modules\Tip\Services;

use App\Modules\Tip\DTOs\TipStoreDTO;
use App\Modules\Tip\DTOs\TipUpdateDTO;
use App\Modules\Tip\Enums\TipEvent;
use App\Modules\Tip\Models\Tip;
use App\Modules\Tip\Models\TipHistory;
use App\Modules\Tip\Repositories\TipRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * Every write here appends a version row inside the same transaction as the write itself. The
 * audit trail is not a side effect that a later refactor may drop: a tip whose text changed
 * without a row saying who and why is exactly the thing this feature exists to prevent.
 */
class TipService
{
    /*
     * The refusals, as i18n keys the SPA translates (`app.rnc/src/locales`). A 422 names its rule; a
     * sentence translated here by the request's locale is a string the SPA cannot look up, and it
     * showed the generic error toast instead.
     */
    private const ANCHOR_TAKEN_KEY = 'tips.errors.anchorTaken';

    private const CONTENT_REQUIRED_KEY = 'tips.errors.contentRequired';

    private const REASON_REQUIRED_KEY = 'tips.errors.reasonRequired';

    public function __construct(
        private TipRepository $tipRepository,
        private TipContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array<int, Tip>
     */
    public function list(bool $includeArchived = false): array
    {
        return $this->tipRepository->findAllTips($includeArchived);
    }

    /**
     * @return array<int, Tip>
     */
    public function active(): array
    {
        return $this->tipRepository->findActiveTips();
    }

    public function find(int $id): ?Tip
    {
        return $this->tipRepository->findTipById($id);
    }

    /**
     * @return array<int, TipHistory>
     */
    public function history(int $id): array
    {
        return $this->tipRepository->findTipHistory($id);
    }

    public function create(TipStoreDTO $dto, ?int $actorId = null): Tip
    {
        $content = $this->sanitizeOrFail($dto->content);
        $this->assertAnchorIsFree($dto->anchor_key);

        return DB::transaction(function () use ($dto, $content, $actorId) {
            $tip = $this->tipRepository->createTip([
                'anchor_key' => $dto->anchor_key,
                'content' => $content,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            /*
             * Version 1 is written on create, not on the first edit. Without it the oldest
             * restorable state would be whatever the tip looked like after someone already
             * changed it — the original wording would be unrecoverable.
             */
            $this->tipRepository->createTipHistory([
                'tip_id' => $tip->id,
                'content' => $content,
                'event' => TipEvent::Created->value,
                'changed_by' => $actorId,
                'change_reason' => $dto->change_reason,
            ]);

            return $this->tipRepository->findTipById($tip->id);
        });
    }

    public function update(int $id, TipUpdateDTO $dto, ?int $actorId = null): ?Tip
    {
        $tip = $this->tipRepository->findTipById($id);

        if (! $tip) {
            return null;
        }

        $content = $dto->content instanceof Optional ? $tip->content : $this->sanitizeOrFail($dto->content);
        $anchorKey = $dto->anchor_key instanceof Optional ? $tip->anchor_key : $dto->anchor_key;
        $reason = $dto->change_reason instanceof Optional ? null : $dto->change_reason;

        if ($anchorKey !== $tip->anchor_key) {
            $this->assertAnchorIsFree($anchorKey);
        }

        $contentChanged = $content !== $tip->content;

        /*
         * The reason is demanded for a CONTENT change only. Moving a tip to another anchor does
         * not rewrite what anyone read, and forcing a sentence for it would train people to type
         * "." to get past the field — which is how a reason column stops meaning anything.
         */
        if ($contentChanged && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'change_reason' => self::REASON_REQUIRED_KEY,
            ]);
        }

        return DB::transaction(function () use ($id, $tip, $content, $anchorKey, $reason, $contentChanged, $actorId) {
            $this->tipRepository->updateTip($id, [
                'anchor_key' => $anchorKey,
                'content' => $content,
                'updated_by' => $actorId,
            ]);

            if ($contentChanged) {
                $this->tipRepository->createTipHistory([
                    'tip_id' => $tip->id,
                    'content' => $content,
                    'event' => TipEvent::Updated->value,
                    'changed_by' => $actorId,
                    'change_reason' => $reason,
                ]);
            }

            return $this->tipRepository->findTipById($id);
        });
    }

    /**
     * Archiving hides the tip and frees its anchor; it never touches the versions. A text can
     * disappear from the app, but not from the record of who took it down.
     */
    public function archive(int $id, ?string $reason = null, ?int $actorId = null): ?Tip
    {
        $tip = $this->tipRepository->findTipById($id);

        if (! $tip || $tip->archived_at !== null) {
            return null;
        }

        return DB::transaction(function () use ($id, $tip, $reason, $actorId) {
            $this->tipRepository->updateTip($id, [
                'archived_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->tipRepository->createTipHistory([
                'tip_id' => $tip->id,
                'content' => $tip->content,
                'event' => TipEvent::Archived->value,
                'changed_by' => $actorId,
                'change_reason' => $reason,
            ]);

            return $this->tipRepository->findTipById($id);
        });
    }

    public function unarchive(int $id, ?int $actorId = null): ?Tip
    {
        $tip = $this->tipRepository->findTipById($id);

        if (! $tip || $tip->archived_at === null) {
            return null;
        }

        // The anchor may have been given to another tip while this one sat archived.
        $this->assertAnchorIsFree($tip->anchor_key);

        return DB::transaction(function () use ($id, $tip, $actorId) {
            $this->tipRepository->updateTip($id, [
                'archived_at' => null,
                'updated_by' => $actorId,
            ]);

            $this->tipRepository->createTipHistory([
                'tip_id' => $tip->id,
                'content' => $tip->content,
                'event' => TipEvent::Unarchived->value,
                'changed_by' => $actorId,
            ]);

            return $this->tipRepository->findTipById($id);
        });
    }

    /**
     * Restoring never rewinds the log: it appends a NEW version carrying the old content and a
     * pointer back to the version it came from. The history a restore was made from stays
     * visible, which is the only way "who put this text back, and from when" survives.
     */
    public function restore(int $id, int $historyId, ?int $actorId = null): ?Tip
    {
        $tip = $this->tipRepository->findTipById($id);

        if (! $tip) {
            return null;
        }

        $version = $this->tipRepository->findTipHistoryEntry($id, $historyId);

        if (! $version) {
            return null;
        }

        return DB::transaction(function () use ($id, $version, $actorId) {
            $this->tipRepository->updateTip($id, [
                'content' => $version->content,
                'updated_by' => $actorId,
            ]);

            $this->tipRepository->createTipHistory([
                'tip_id' => $id,
                'content' => $version->content,
                'event' => TipEvent::Restored->value,
                'restored_from_id' => $version->id,
                'changed_by' => $actorId,
            ]);

            return $this->tipRepository->findTipById($id);
        });
    }

    private function assertAnchorIsFree(string $anchorKey): void
    {
        if (! $this->tipRepository->findActiveTipByAnchor($anchorKey)) {
            return;
        }

        throw ValidationException::withMessages([
            'anchor_key' => self::ANCHOR_TAKEN_KEY,
        ]);
    }

    private function sanitizeOrFail(string $content): string
    {
        $sanitized = $this->sanitizer->sanitize($content);

        if (! $this->sanitizer->hasText($sanitized)) {
            throw ValidationException::withMessages([
                'content' => self::CONTENT_REQUIRED_KEY,
            ]);
        }

        return $sanitized;
    }
}
