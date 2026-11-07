<?php

namespace App\Modules\Partner\Resources;

use App\Helpers\Brazil\PhoneNumber;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Helpers\ContractValueBand;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\ReferralSla;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A referral as its partner sees it (rnc-2 R6, D3) — an allow-list, field by field: the company,
 * sector · regime · porte, the titles of the items its specialty covers, the score, Franco's
 * message, the dates, the status and the SLA. The lead's contact only from the accept on (a partner
 * that declines never saw a phone); a portfolio client's (`carteira`) way in is Franco's own phone
 * (stored as typed, answered as digits with the 55, like a lead's) and mailbox. The contract value only once closed, and it is the partner's own number. Never: a
 * range, a note, the timeline, the consent, another referral. Moments in UTC, ISO-8601.
 *
 * A legal referral of a client contract (rnc-3 R21, AM20) adds `contract`: its kind, end year,
 * whether it still lacks the Reforma clause and its annual value as a fixed band
 * (`ContractValueBand`) — never the exact value, the client's fee, its other contracts or its other
 * referrals. Every other referral answers `contract: null`.
 *
 * @mixin Referral
 */
class PortalReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = ReferralStatus::from($this->status);
        $profile = $this->lead->profile ?? [];

        return [
            'id' => $this->id,
            'status' => $this->status,
            'received_at' => $this->moment($this->created_at),
            'status_at' => $this->moment($this->status_at),
            'company' => $this->lead->company,
            'profile' => ['sector' => $profile['sector'] ?? null, 'regime' => $profile['regime'] ?? null, 'band' => $profile['band'] ?? null],
            'covers' => array_column($this->covers, 'title'),
            'score' => $this->score,
            'message' => $this->message,
            'sla' => app(ReferralSla::class)->of($this->resource),
            'contacted_at' => $this->moment($this->contacted_at),
            'meeting_at' => $this->moment($this->meeting_at),
            'closed_at' => $this->moment($this->closed_at),
            'contact' => $status->showsContact() ? $this->contact() : null,
            'contract' => $this->contractSummary(),
            ...($status === ReferralStatus::Fechado ? ['deal_value_cents' => $this->deal_value_cents] : []),
        ];
    }

    /** @return array{via: string, name: string|null, phone: string|null, email: string|null} */
    private function contact(): array
    {
        if ($this->lead->channel === LeadChannel::Carteira->value) {
            $firm = app(CurrentFirm::class)->get();

            return ['via' => 'firm', 'name' => $firm?->name, 'phone' => PhoneNumber::normalize($firm?->phone), 'email' => $firm?->mailbox];
        }

        return ['via' => 'lead', 'name' => $this->lead->contact_name, 'phone' => $this->lead->phone, 'email' => $this->lead->email];
    }

    /** @return array{kind: string, end_year: int, sem_clausula: bool, value_range: array{min_cents: int, max_cents: int|null, label: string}}|null */
    private function contractSummary(): ?array
    {
        $contract = $this->contract_id === null ? null : $this->contract;

        if ($contract === null) {
            return null;
        }

        return [
            'kind' => $contract->kind,
            'end_year' => (int) $contract->end_year,
            'sem_clausula' => ! $contract->has_reforma_clause,
            'value_range' => ContractValueBand::of((int) $contract->annual_value_cents),
        ];
    }

    private function moment(?CarbonInterface $at): ?string
    {
        return $at?->toImmutable()->utc()->toIso8601String();
    }
}
