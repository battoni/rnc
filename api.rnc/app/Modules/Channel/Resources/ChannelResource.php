<?php

namespace App\Modules\Channel\Resources;

use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Channel\Services\ChannelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "Canal RNC" board (rnc-3 R8, R9), built from `ChannelService::board()`: the summary (Franco's
 * share Σ a month and a year, Σ to come, counts by stage) and one `ChannelFirmResource` per firm.
 *
 * Closed by default (AM15): without `withPrices()` no row carries `monthly_fee_cents`, `share_bp` or
 * `notes`, and there are no `defaults` — Reinaldo's read. Every write answers with prices, since only
 * `channel.manage` reaches one.
 *
 * @phpstan-import-type Board from ChannelService
 */
class ChannelResource extends JsonResource
{
    private bool $withPrices = false;

    public function withPrices(bool $withPrices = true): self
    {
        $this->withPrices = $withPrices;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var Board $board */
        $board = $this->resource;

        $answer = [
            'summary' => $board['summary'],
            'firms' => $board['firms']->map(fn (ChannelFirm $firm) => (new ChannelFirmResource($firm))->withPrices($this->withPrices)->toArray($request))->values()->all(),
        ];

        return ! $this->withPrices ? $answer : [...$answer, 'defaults' => $board['defaults']];
    }
}
