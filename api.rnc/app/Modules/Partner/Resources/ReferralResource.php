<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\ReferralSla;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the trail (R23): the lead (by company), the partner, the consent as it was given
 * (text, version, when), who referred it, when, the message and the status — and, since the partner
 * moves it from its portal (rnc-2 R10), when it last moved, its first-contact SLA and, once closed,
 * the first-year contract value (`null` before). No contact data of the lead: the trail is about
 * the hand-off, not the person.
 *
 * @mixin Referral
 */
class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'at' => $this->created_at->toIso8601String(),
            'status' => $this->status,
            'status_at' => $this->status_at?->toImmutable()->utc()->toIso8601String(),
            'sla' => app(ReferralSla::class)->of($this->resource),
            'deal_value_cents' => $this->status === ReferralStatus::Fechado->value ? $this->deal_value_cents : null,
            'score' => $this->score,
            'covers' => $this->covers,
            'message' => $this->message,
            'contract_id' => $this->contract_id,
            'lead' => ['id' => (int) $this->lead_id, 'company' => $this->lead->company],
            'partner' => [
                'id' => (int) $this->partner_id,
                'name' => $this->partner->name,
                'specialty' => $this->partner->specialty,
                'active' => $this->partner->active,
            ],
            'referred_by' => ['id' => (int) $this->referred_by, 'name' => trim($this->referrer->name.' '.$this->referrer->last_name)],
            'consent' => [
                'given' => true,
                'text' => $this->consent_text,
                'version' => $this->consent_version,
                'at' => $this->consent_given_at->toIso8601String(),
            ],
        ];
    }
}
