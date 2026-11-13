<?php

namespace App\Modules\Channel\Repositories;

use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Channel\Models\ChannelFirmEvent;
use Illuminate\Database\Eloquent\Collection;

/** The current firm's channel firms and their moves. `BelongsToFirm` scopes and stamps the firm. */
class ChannelRepository
{
    /** @return Collection<int, ChannelFirm> in the order they joined the channel */
    public function findAllChannelFirms(): Collection
    {
        return ChannelFirm::query()->orderBy('id')->get();
    }

    public function findChannelFirmById(int $id): ?ChannelFirm
    {
        return ChannelFirm::query()->find($id);
    }

    /** The row a move reads and writes, held until the transaction ends so two clicks cannot both pass. */
    public function findChannelFirmForUpdate(int $id): ?ChannelFirm
    {
        return ChannelFirm::query()->lockForUpdate()->find($id);
    }

    /**
     * The board's sums in one grouped query (plan P13): one line per stage, fee and share alike,
     * with how many firms share it — so each firm's share is rounded before it is summed.
     *
     * @return list<array{stage: string, monthly_fee_cents: int, share_bp: int, firm_count: int}>
     */
    public function summarizeChannelFirms(): array
    {
        return ChannelFirm::query()
            ->toBase()
            ->select(['stage', 'monthly_fee_cents', 'share_bp'])
            ->selectRaw('COUNT(*) AS firm_count')
            ->groupBy(['stage', 'monthly_fee_cents', 'share_bp'])
            ->get()
            ->map(fn (object $row) => [
                'stage' => (string) $row->stage,
                'monthly_fee_cents' => (int) $row->monthly_fee_cents,
                'share_bp' => (int) $row->share_bp,
                'firm_count' => (int) $row->firm_count,
            ])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $attributes */
    public function createChannelFirm(array $attributes): ChannelFirm
    {
        return ChannelFirm::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateChannelFirm(ChannelFirm $firm, array $attributes): ChannelFirm
    {
        $firm->update($attributes);

        return $firm;
    }

    /** @param  array{channel_firm_id: int, user_id: int, from: string, to: string, at: mixed}  $attributes */
    public function createChannelFirmEvent(array $attributes): ChannelFirmEvent
    {
        return ChannelFirmEvent::query()->create($attributes);
    }
}
