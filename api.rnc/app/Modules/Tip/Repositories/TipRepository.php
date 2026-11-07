<?php

namespace App\Modules\Tip\Repositories;

use App\Modules\Tip\Models\Tip;
use App\Modules\Tip\Models\TipHistory;

class TipRepository
{
    public function __construct(
        private Tip $model,
        private TipHistory $historyModel,
    ) {}

    /**
     * @return array<int, Tip>
     */
    public function findAllTips(bool $includeArchived = false): array
    {
        $query = $this->model->newQuery()->with(['createdBy', 'updatedBy']);

        if (! $includeArchived) {
            $query->whereNull('archived_at');
        }

        return $query->orderBy('anchor_key')->get()->all();
    }

    /**
     * The payload every authenticated user gets to render tips with: active rows only, and only
     * the two columns a popover needs.
     *
     * @return array<int, Tip>
     */
    public function findActiveTips(): array
    {
        return $this->model->newQuery()
            ->whereNull('archived_at')
            ->orderBy('anchor_key')
            ->get(['id', 'anchor_key', 'content'])
            ->all();
    }

    public function findTipById(int $id): ?Tip
    {
        return $this->model->with(['createdBy', 'updatedBy'])->find($id);
    }

    public function findActiveTipByAnchor(string $anchorKey): ?Tip
    {
        return $this->model->newQuery()
            ->where('anchor_key', $anchorKey)
            ->whereNull('archived_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTip(array $data): Tip
    {
        return $this->model->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTip(int $id, array $data): bool
    {
        return (bool) $this->model->where('id', $id)->update($data);
    }

    /**
     * @return array<int, TipHistory>
     */
    public function findTipHistory(int $tipId): array
    {
        return $this->historyModel->newQuery()
            ->with('changedBy')
            ->where('tip_id', $tipId)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function findTipHistoryEntry(int $tipId, int $historyId): ?TipHistory
    {
        return $this->historyModel->newQuery()
            ->where('tip_id', $tipId)
            ->where('id', $historyId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTipHistory(array $data): TipHistory
    {
        return $this->historyModel->create($data);
    }
}
