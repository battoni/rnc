<?php

namespace App\Modules\Partner\Repositories;

use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Models\PartnerAccessEvent;
use App\Modules\Partner\Models\Referral;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the ledger reads (rnc-2 R11): a month's closings and the access history, one query each for
 * the whole network or one partner — never a query per partner (plan AM6's rule, applied here).
 * `BelongsToFirm` keeps both inside the current firm. Bounds are UTC: a `Carbon` binding is written
 * as its wall-clock time, so a São Paulo bound would shift by three hours.
 */
class LedgerRepository
{
    /** @return Collection<int, Referral> closed in [from, to], newest first; the lead's company when asked */
    public function findClosedReferralsBetween(CarbonInterface $from, CarbonInterface $to, ?int $partnerId = null, bool $withCompany = false): Collection
    {
        return Referral::query()
            ->select(['id', 'firm_id', 'lead_id', 'partner_id', 'deal_value_cents', 'closed_at'])
            ->where('status', ReferralStatus::Fechado->value)
            ->whereBetween('closed_at', [$from->utc(), $to->utc()])
            ->when($partnerId !== null, fn ($query) => $query->where('partner_id', $partnerId))
            ->when($withCompany, fn ($query) => $query->with('lead:id,firm_id,company'))
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, PartnerAccessEvent> every grant and revoke up to `to`, per partner in time order */
    public function findPartnerAccessEventsUntil(CarbonInterface $to, ?int $partnerId = null): Collection
    {
        return PartnerAccessEvent::query()
            ->select(['id', 'firm_id', 'partner_id', 'type', 'at'])
            ->where('at', '<=', $to->utc())
            ->when($partnerId !== null, fn ($query) => $query->where('partner_id', $partnerId))
            ->orderBy('partner_id')
            ->orderBy('at')
            ->orderBy('id')
            ->get();
    }
}
