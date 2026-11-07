<?php

namespace App\Modules\Partner\Repositories;

use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Referral;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** The trail's rows. `BelongsToFirm` keeps every query inside the current firm. */
class ReferralRepository
{
    private const TRAIL_RELATIONS = ['lead:id,company', 'partner:id,name,specialty,active', 'referrer:id,name,last_name'];

    /** @param  array<string, mixed>  $attributes */
    public function createReferral(array $attributes): Referral
    {
        return Referral::query()->create($attributes);
    }

    /** The lead's own referral to this partner — a contract referral of it is another subject (rnc-3 AM11). */
    public function existsReferralOfLeadToPartner(Lead $lead, int $partnerId): bool
    {
        return Referral::query()->where('lead_id', $lead->id)->where('partner_id', $partnerId)->where('subject_key', Referral::SUBJECT_LEAD)->exists();
    }

    /** A legal referral of this contract to this partner (rnc-3 R20): one per pair. */
    public function existsReferralOfContractToPartner(int $contractId, int $partnerId): bool
    {
        return Referral::query()->where('subject_key', Referral::contractSubject($contractId))->where('partner_id', $partnerId)->exists();
    }

    /** @return Collection<int, Referral> the lead's own referrals (not its contracts'), keyed by partner id */
    public function findReferralsOfLead(Lead $lead): Collection
    {
        return Referral::query()->where('lead_id', $lead->id)->where('subject_key', Referral::SUBJECT_LEAD)->get()->keyBy('partner_id');
    }

    /**
     * The partners each of these leads was already sent to (rnc-2 R27), as lead id → partner ids —
     * the leads' own referrals: a contract referral of the same client never counts (rnc-3 AM11). One query.
     *
     * @param  list<int>  $leadIds
     * @return array<int, list<int>>
     */
    public function findPartnerIdsByLeads(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $pairs = [];

        foreach (Referral::query()->whereIn('lead_id', $leadIds)->where('subject_key', Referral::SUBJECT_LEAD)->get(['lead_id', 'partner_id']) as $row) {
            $pairs[(int) $row->lead_id][] = (int) $row->partner_id;
        }

        return $pairs;
    }

    /** @return LengthAwarePaginator<int, Referral> newest first */
    public function paginateReferrals(?int $partnerId, int $perPage): LengthAwarePaginator
    {
        return Referral::query()
            ->with(self::TRAIL_RELATIONS)
            ->when($partnerId !== null, fn ($query) => $query->where('partner_id', $partnerId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function loadReferralForTrail(Referral $referral): Referral
    {
        return $referral->load(self::TRAIL_RELATIONS);
    }
}
