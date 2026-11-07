<?php

namespace App\Modules\Channel\Resources;

use App\Modules\Channel\Enums\ChannelStage;
use App\Modules\Channel\Helpers\ChannelShare;
use App\Modules\Channel\Models\ChannelFirm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the "Canal RNC" board (rnc-3 R8, R9). Closed by default (AM15): name, city, stage, when
 * it got there, the next stage and Franco's share — what Reinaldo sees. `withPrices()` adds the
 * platform's fee, the share rate and the operator's notes, for `channel.manage` only: the platform
 * path is undecided (`commercial.md:41`) and must be settled before Reinaldo sees its price.
 *
 * @mixin ChannelFirm
 */
class ChannelFirmResource extends JsonResource
{
    private bool $withPrices = false;

    public function withPrices(bool $withPrices = true): self
    {
        $this->withPrices = $withPrices;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $row = [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'stage' => $this->stage,
            'stage_at' => $this->stage_at->toIso8601String(),
            'next_stage' => ChannelStage::from($this->stage)->next()?->value,
            'share_cents' => ChannelShare::of($this->monthly_fee_cents, $this->share_bp),
        ];

        return ! $this->withPrices ? $row : [
            ...$row,
            'monthly_fee_cents' => $this->monthly_fee_cents,
            'share_bp' => $this->share_bp,
            'notes' => $this->notes,
        ];
    }
}
