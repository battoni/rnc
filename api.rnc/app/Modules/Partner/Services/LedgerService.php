<?php

namespace App\Modules\Partner\Services;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Partner\Enums\PartnerAccessEventType;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\PartnerAccessEvent;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Repositories\LedgerRepository;
use App\Modules\Partner\Repositories\PartnerRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The network ledger (rnc-2 R11, D10; plan AM9): what each partner owes the firm in a month, under
 * the firm's one money model — figures to check, never a charge.
 *
 * - **Comissão**: per closing, `intdiv(deal_value_cents * rate_bp + 5000, 10000)` — the rate in
 *   basis points, half-up to the cent, each closing on its own.
 * - **Assinatura**: the monthly fee for each partner whose portal access existed on any day of the
 *   month, folded from `partner_access_events` (a revoke and a later regrant bill both periods).
 *   Closings are listed and add nothing.
 *
 * A closing belongs to the month of its `closed_at` in São Paulo. Each statement asks three
 * queries whatever the number of partners: the closings, the access history and (network only)
 * the partners; the maths is pure PHP.
 *
 * @phpstan-type StatementLine array{kind: string, referral_id: int|null, company: string|null, closed_at: CarbonImmutable|null, deal_value_cents: int|null, amount_cents: int}
 * @phpstan-type PartnerStatement array{month: string, model: PartnerMoneyModel, firm_name: string, commission_rate_bp: int, monthly_fee_cents: int, lines: list<StatementLine>, deals: int, deal_total_cents: int, total_cents: int}
 * @phpstan-type NetworkRow array{partner: Partner, deals: int, deal_total_cents: int, commission_cents: int, subscribed: bool, subscription_cents: int, due_cents: int}
 * @phpstan-type NetworkStatement array{month: string, model: PartnerMoneyModel, commission_rate_bp: int, monthly_fee_cents: int, partners: list<NetworkRow>, deals: int, deal_total_cents: int, total_cents: int, other_model: PartnerMoneyModel, other_total_cents: int}
 * @phpstan-type AccessEvent array{partner_id: int, type: PartnerAccessEventType, at: CarbonInterface}
 */
class LedgerService
{
    public const TIME_ZONE = 'America/Sao_Paulo';

    public const LINE_SUBSCRIPTION = 'subscription';

    public const LINE_CLOSING = 'closing';

    private const BASIS_POINTS = 10000;

    private const HALF_CENT = 5000;

    public function __construct(
        private CurrentFirm $currentFirm,
        private LedgerRepository $ledgerRepository,
        private PartnerRepository $partnerRepository,
    ) {}

    /** The month a request without `?month=` reads: today's, in São Paulo. */
    public static function currentMonth(): string
    {
        return CarbonImmutable::now(self::TIME_ZONE)->format('Y-m');
    }

    /** One closing's commission: the rate in basis points of the value, half-up to the cent (AM9). */
    public static function commission(int $dealValueCents, int $rateBp): int
    {
        return intdiv($dealValueCents * $rateBp + self::HALF_CENT, self::BASIS_POINTS);
    }

    /**
     * The partners whose access was open at some moment of [from, to]: each partner's grants and
     * revokes, in time order, are folded into open periods. A revoke with no grant before it is
     * ignored; a period still open at the end counts when it began by `to`.
     *
     * @param  iterable<AccessEvent>  $events
     * @return list<int> ascending
     */
    public static function subscribedPartnerIds(iterable $events, CarbonInterface $from, CarbonInterface $to): array
    {
        $openSince = [];
        $billed = [];

        foreach ($events as $event) {
            $partnerId = $event['partner_id'];
            $since = $openSince[$partnerId] ?? null;

            if ($event['type'] === PartnerAccessEventType::Granted) {
                $openSince[$partnerId] = $since ?? $event['at'];

                continue;
            }

            $periodTouchesMonth = $since !== null && $since->lessThanOrEqualTo($to) && $event['at']->greaterThan($from);

            if ($periodTouchesMonth) {
                $billed[$partnerId] = true;
            }

            unset($openSince[$partnerId]);
        }

        foreach ($openSince as $partnerId => $since) {
            if ($since->lessThanOrEqualTo($to)) {
                $billed[$partnerId] = true;
            }
        }

        $ids = array_keys($billed);
        sort($ids);

        return $ids;
    }

    /**
     * The partner's own month (the portal's "Extrato"): its closings and, under assinatura, the fee
     * line when its access existed in the month. Only this partner's rows are read.
     *
     * @return PartnerStatement
     */
    public function statementFor(Partner $partner, string $month): array
    {
        $firm = $this->firm();
        $model = $firm->partnerMoneyModel();
        [$from, $to] = self::bounds($month);
        $closings = $this->ledgerRepository->findClosedReferralsBetween($from, $to, $partner->id, withCompany: true);
        $subscribed = in_array($partner->id, $this->subscribedIn($from, $to, $partner->id), true);
        $byCommission = $model === PartnerMoneyModel::Comissao;

        $lines = $closings->map(fn (Referral $referral) => [
            'kind' => self::LINE_CLOSING,
            'referral_id' => $referral->id,
            'company' => $referral->lead->company,
            'closed_at' => $referral->closed_at,
            'deal_value_cents' => (int) $referral->deal_value_cents,
            'amount_cents' => $byCommission ? self::commission((int) $referral->deal_value_cents, $firm->partner_commission_rate_bp) : 0,
        ])->values()->all();

        if (! $byCommission && $subscribed) {
            array_unshift($lines, [
                'kind' => self::LINE_SUBSCRIPTION,
                'referral_id' => null,
                'company' => null,
                'closed_at' => null,
                'deal_value_cents' => null,
                'amount_cents' => $firm->partner_monthly_fee_cents,
            ]);
        }

        return [
            'month' => $month,
            'model' => $model,
            'firm_name' => $firm->name,
            'commission_rate_bp' => $firm->partner_commission_rate_bp,
            'monthly_fee_cents' => $firm->partner_monthly_fee_cents,
            'lines' => $lines,
            'deals' => $closings->count(),
            'deal_total_cents' => (int) $closings->sum('deal_value_cents'),
            'total_cents' => array_sum(array_column($lines, 'amount_cents')),
        ];
    }

    /**
     * Franco's "Extrato da rede": every partner's month under both models, the total under the
     * firm's and what the other would give (the comparison is Franco's alone, D10).
     *
     * @return NetworkStatement
     */
    public function networkStatement(string $month): array
    {
        $firm = $this->firm();
        $model = $firm->partnerMoneyModel();
        [$from, $to] = self::bounds($month);
        $closingsByPartner = $this->ledgerRepository->findClosedReferralsBetween($from, $to)->groupBy('partner_id');
        $subscribed = array_flip($this->subscribedIn($from, $to));

        $rows = $this->partnerRepository->findPartnersByName()->map(function (Partner $partner) use ($closingsByPartner, $subscribed, $firm, $model) {
            /** @var Collection<int, Referral> $closings */
            $closings = $closingsByPartner->get($partner->id, collect());
            $commission = $closings->sum(fn (Referral $referral) => self::commission((int) $referral->deal_value_cents, $firm->partner_commission_rate_bp));
            $isSubscribed = isset($subscribed[$partner->id]);
            $subscription = $isSubscribed ? $firm->partner_monthly_fee_cents : 0;

            return [
                'partner' => $partner,
                'deals' => $closings->count(),
                'deal_total_cents' => (int) $closings->sum('deal_value_cents'),
                'commission_cents' => (int) $commission,
                'subscribed' => $isSubscribed,
                'subscription_cents' => $subscription,
                'due_cents' => $model === PartnerMoneyModel::Comissao ? (int) $commission : $subscription,
            ];
        })->values()->all();

        $totals = [
            PartnerMoneyModel::Comissao->value => array_sum(array_column($rows, 'commission_cents')),
            PartnerMoneyModel::Assinatura->value => array_sum(array_column($rows, 'subscription_cents')),
        ];

        return [
            'month' => $month,
            'model' => $model,
            'commission_rate_bp' => $firm->partner_commission_rate_bp,
            'monthly_fee_cents' => $firm->partner_monthly_fee_cents,
            'partners' => $rows,
            'deals' => array_sum(array_column($rows, 'deals')),
            'deal_total_cents' => array_sum(array_column($rows, 'deal_total_cents')),
            'total_cents' => $totals[$model->value],
            'other_model' => $model->other(),
            'other_total_cents' => $totals[$model->other()->value],
        ];
    }

    /**
     * The month's first and last second in São Paulo (`YYYY-MM`, validated by the request).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function bounds(string $month): array
    {
        $start = CarbonImmutable::parse("{$month}-01 00:00:00", self::TIME_ZONE);

        return [$start, $start->endOfMonth()];
    }

    /** @return list<int> */
    private function subscribedIn(CarbonImmutable $from, CarbonImmutable $to, ?int $partnerId = null): array
    {
        $events = $this->ledgerRepository->findPartnerAccessEventsUntil($to, $partnerId)->map(fn (PartnerAccessEvent $event) => [
            'partner_id' => $event->partner_id,
            'type' => PartnerAccessEventType::from($event->type),
            'at' => $event->at,
        ]);

        return self::subscribedPartnerIds($events, $from, $to);
    }

    private function firm(): Firm
    {
        /** @var Firm $firm every route here is authenticated, so `ResolveCurrentFirm` set the firm */
        $firm = $this->currentFirm->get();

        return $firm;
    }
}
