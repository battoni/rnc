<?php

namespace App\Modules\Briefing\Repositories;

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Briefing\Helpers\FeeQuartiles;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The briefing's reads of other modules' tables (rnc-3 R23, R24), each one query whatever the
 * number of rows (plan P13) and each firm-scoped by the models' own `BelongsToFirm`. A portfolio
 * (`carteira`) lead is left out of every lead figure here; a referral counts whatever its lead.
 */
class BriefingRepository
{
    private const CEO_ROLE = 'ceo';

    /**
     * Leads that left a contact since `$since` (the demo's `l.contact`), carteira left out.
     * `created_at` is a plain timestamp, stored in the app's zone — unlike the `UtcDateTime` columns.
     */
    public function countNewLeadsSince(CarbonImmutable $since): int
    {
        return $this->prospects()
            ->where('created_at', '>=', $since->setTimezone((string) config('app.timezone')))
            ->where(fn (Builder $query) => $query->whereNotNull('phone')->orWhereNotNull('email'))
            ->count();
    }

    /**
     * The partial leads and the open pipeline (lead · reuniao_marcada · proposta), carteira left
     * out — only what the figures read: the stage and the profile's band.
     *
     * @param  list<LeadStage>  $stages
     * @return Collection<int, Lead>
     */
    public function findLeadsInStages(array $stages): Collection
    {
        return $this->prospects()
            ->whereIn('stage', array_map(fn (LeadStage $stage) => $stage->value, $stages))
            ->get(['id', 'stage', 'profile']);
    }

    /**
     * Every client's monthly fee in cents, by band — one query, the quartiles taken in PHP.
     *
     * @return array<string, list<int>>
     */
    public function findMonthlyFeesByBand(): array
    {
        $fees = [];

        foreach (Client::query()->toBase()->orderBy('band')->get(['band', 'monthly_fee']) as $row) {
            $fees[(string) $row->band][] = FeeQuartiles::cents($row->monthly_fee);
        }

        return $fees;
    }

    /**
     * Referrals still owed a first contact — not contacted, not declined — with the lead's company
     * and the partner's name: three queries whatever their number.
     *
     * @return Collection<int, Referral>
     */
    public function findReferralsAwaitingContact(): Collection
    {
        return Referral::query()
            ->with(['lead:id,company', 'partner:id,name'])
            ->whereNull('contacted_at')
            ->where('status', '!=', ReferralStatus::Recusado->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'lead_id', 'partner_id', 'status', 'created_at', 'contacted_at']);
    }

    /**
     * The firm's active ceos with an e-mail — who the 07:00 briefing goes to (R27). `users` is not
     * firm-scoped (Firm RULES.md), so the firm is named.
     *
     * @return Collection<int, User>
     */
    public function findActiveCeosOfFirm(int $firmId): Collection
    {
        return User::query()
            ->where('firm_id', $firmId)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereNotNull('email')
            ->whereHas('role', fn (Builder $query) => $query->where('slug', self::CEO_ROLE))
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'firm_id', 'role_id', 'status']);
    }

    /** @return Builder<Lead> */
    private function prospects(): Builder
    {
        return Lead::query()->where('channel', '!=', LeadChannel::Carteira->value);
    }
}
