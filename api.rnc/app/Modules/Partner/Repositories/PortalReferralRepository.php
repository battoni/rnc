<?php

namespace App\Modules\Partner\Repositories;

use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\CurrentPartner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The portal's referrals (rnc-2 R4, D2): every query is the current firm's (`BelongsToFirm`) AND
 * the signed-in partner's — `where partner_id = CurrentPartner::id()`, never an id from the request.
 * No partner set (a route without the `portal` alias) asks `partner_id IS NULL` — a column that is
 * never null — and finds nothing.
 */
class PortalReferralRepository
{
    /**
     * What the portal's resource reads of the lead (its company, profile, contact and channel) and of
     * a legal referral's contract (rnc-3 R21: kind, end year, clause, value — the value only to band it).
     */
    private const RELATIONS = ['lead:id,firm_id,company,profile,contact_name,phone,email,channel', 'contract:id,firm_id,kind,end_year,annual_value_cents,has_reforma_clause'];

    public function __construct(private CurrentPartner $currentPartner) {}

    /** @return Collection<int, Referral> newest first; the inbox puts the closed ones last */
    public function findPartnerReferrals(): Collection
    {
        return $this->ofPartner()->with(self::RELATIONS)->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    public function findPartnerReferralById(int $id): ?Referral
    {
        return $this->ofPartner()->with(self::RELATIONS)->find($id);
    }

    /** The row to move, locked until the transaction that moves it ends. */
    public function findPartnerReferralForUpdate(int $id): ?Referral
    {
        return $this->ofPartner()->with(self::RELATIONS)->lockForUpdate()->find($id);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateReferral(Referral $referral, array $attributes): Referral
    {
        $referral->update($attributes);

        return $referral;
    }

    /** @return Builder<Referral> */
    private function ofPartner(): Builder
    {
        return Referral::query()->where('partner_id', $this->currentPartner->id());
    }
}
