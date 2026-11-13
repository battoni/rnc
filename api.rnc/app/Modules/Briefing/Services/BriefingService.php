<?php

namespace App\Modules\Briefing\Services;

use App\Modules\Booking\Helpers\Holidays;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Booking\Services\BookingCalendar;
use App\Modules\Briefing\Helpers\ActionPicker;
use App\Modules\Briefing\Helpers\FeeQuartiles;
use App\Modules\Briefing\Repositories\BriefingRepository;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\ReferralSla;
use App\Modules\Radar\Services\RadarService;
use Carbon\CarbonImmutable;

/**
 * Reinaldo's day in one payload (rnc-3 R23–R25; the demo's `briefingNumbers`, `ceo.js`), for the
 * screen (`GET briefing`) and the 07:00 e-mail alike, under the current firm:
 *
 * - **leads** — those that left a contact in the last 7 days and in the last 24 hours;
 * - **meetings** — from today 00:00 to the end of the 5th business day (weekends and national
 *   holidays skipped, today counts when it is one), the lost leads' left out; `today`; the first 5;
 * - **referrals** — every referral still owed a first contact, by SLA state (`ReferralSla`, rnc-2
 *   R8), breached and longest first; the first 5 and how many more;
 * - **pipeline** — lead · reuniao_marcada · proposta, counted and valued at the portfolio's fee
 *   quartiles × 12 (`FeeQuartiles`, R24), in total and by stage;
 * - **actions** — the day's first three (`ActionPicker`, R25).
 *
 * Portfolio (`carteira`) leads count in no lead, meeting or pipeline figure; the referral tile
 * counts every open referral, whatever its lead. A fixed number of queries (plan P13): two lead
 * counts, the stages' leads, the fees, the meetings with their lead and specialist, the referrals
 * with their lead and partner.
 *
 * @phpstan-type Range array{0: int, 1: int}|null
 * @phpstan-type Briefing array{
 *     as_of: string,
 *     leads: array{last_7_days: int, last_24_hours: int},
 *     meetings: array{count: int, today: int, window_end: string, next: list<array{lead_id: int, company: string|null, starts_at: string, specialist: string}>},
 *     referrals: array{count: int, by_state: array{ok: int, atencao: int, estourado: int}, goal_hours: int, top: list<array{id: int, lead_id: int, company: string|null, partner: array{id: int, name: string}, state: string, hours: float}>, more: int},
 *     pipeline: array{count: int, range_cents: Range, stages: list<array{stage: string, count: int, range_cents: Range}>},
 *     actions: list<array{kind: string, params: array<string, mixed>, to: string}>,
 * }
 */
class BriefingService
{
    public const LEAD_DAYS = 7;

    public const MEETING_BUSINESS_DAYS = 5;

    public const LIST_SIZE = 5;

    private const OPEN_STAGES = [LeadStage::Lead, LeadStage::ReuniaoMarcada, LeadStage::Proposta];

    private const SLA_ORDER = [ReferralSla::BREACHED => 0, ReferralSla::WARNING => 1, ReferralSla::OK => 2];

    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private BriefingRepository $briefingRepository,
        private AppointmentRepository $appointmentRepository,
        private ReferralSla $referralSla,
        private RadarService $radarService,
    ) {}

    /** @return Briefing */
    public function build(): array
    {
        $now = CarbonImmutable::now();
        $meetings = $this->meetings($now);
        $referrals = $this->referrals();
        $leads = $this->briefingRepository->findLeadsInStages([LeadStage::LeadParcial, ...self::OPEN_STAGES]);
        $pipeline = $this->pipeline($leads, new FeeQuartiles($this->briefingRepository->findMonthlyFeesByBand()));
        $first = $referrals['top'][0] ?? null;

        return [
            'as_of' => BookingCalendar::localIso($now),
            'leads' => [
                'last_7_days' => $this->briefingRepository->countNewLeadsSince($now->subDays(self::LEAD_DAYS)),
                'last_24_hours' => $this->briefingRepository->countNewLeadsSince($now->subDay()),
            ],
            'meetings' => $meetings,
            'referrals' => $referrals,
            'pipeline' => $pipeline,
            'actions' => ActionPicker::pick([
                'breached' => $first !== null && $first['state'] === ReferralSla::BREACHED
                    ? ['partner_id' => $first['partner']['id'], 'partner' => $first['partner']['name'], 'company' => (string) $first['company'], 'hours' => $first['hours']]
                    : null,
                'meetings_today' => $meetings['today'],
                'proposals' => ['count' => $pipeline['stages'][2]['count'], 'range_cents' => $pipeline['stages'][2]['range_cents']],
                'partial_leads' => $leads->where('stage', LeadStage::LeadParcial->value)->count(),
                'radar' => $this->radarOpportunity(),
            ]),
        ];
    }

    /**
     * The Radar's first opportunity type (R25's fifth action, W81): the same row `GET radar` shows
     * first among the opportunities — its rule, title, clients and range. Null when the portfolio
     * has none. Radar's fixed cost: the clients and one sales aggregate (`firstOpportunity`, W100).
     *
     * @return array{rule: string, title: string, clients: int, range_cents: array{0: int, 1: int}, period: string}|null
     */
    private function radarOpportunity(): ?array
    {
        $first = $this->radarService->firstOpportunity();

        return $first === null ? null : [
            'rule' => $first['rule'],
            'title' => $first['title'],
            'clients' => $first['clients'],
            'range_cents' => $first['range_cents'],
            'period' => $first['period'],
        ];
    }

    /** @return array{count: int, today: int, window_end: string, next: list<array{lead_id: int, company: string|null, starts_at: string, specialist: string}>} */
    private function meetings(CarbonImmutable $now): array
    {
        $today = $now->setTimezone(BookingCalendar::TIMEZONE)->startOfDay();
        $end = $this->afterBusinessDays($today, self::MEETING_BUSINESS_DAYS);
        $tomorrow = $today->addDay();

        $meetings = $this->appointmentRepository->findAppointmentsBetween($today, $end)
            ->filter(fn (Appointment $appointment) => $appointment->lead->stage !== LeadStage::Perdido->value
                && $appointment->lead->channel !== LeadChannel::Carteira->value)
            ->values();

        return [
            'count' => $meetings->count(),
            'today' => $meetings->filter(fn (Appointment $appointment) => $appointment->starts_at < $tomorrow)->count(),
            'window_end' => $end->subDay()->format(self::DATE_FORMAT),
            'next' => $meetings->take(self::LIST_SIZE)->map(fn (Appointment $appointment) => [
                'lead_id' => $appointment->lead_id,
                'company' => $appointment->lead->company,
                'starts_at' => BookingCalendar::localIso($appointment->starts_at),
                'specialist' => $appointment->specialist->displayName(),
            ])->all(),
        ];
    }

    /** The midnight that closes the `$days`-th business day counted from `$day` (itself included). */
    private function afterBusinessDays(CarbonImmutable $day, int $days): CarbonImmutable
    {
        $left = $days;

        while ($left > 0) {
            if (! $day->isWeekend() && ! Holidays::isHoliday($day)) {
                $left--;
            }

            $day = $day->addDay();
        }

        return $day;
    }

    /** @return array{count: int, by_state: array{ok: int, atencao: int, estourado: int}, goal_hours: int, top: list<array{id: int, lead_id: int, company: string|null, partner: array{id: int, name: string}, state: string, hours: float}>, more: int} */
    private function referrals(): array
    {
        $open = $this->briefingRepository->findReferralsAwaitingContact()
            ->map(fn (Referral $referral) => ['referral' => $referral, ...$this->referralSla->of($referral)])
            ->filter(fn (array $row) => isset(self::SLA_ORDER[$row['state']]))
            ->sort(fn (array $a, array $b) => [self::SLA_ORDER[$a['state']], $b['hours']] <=> [self::SLA_ORDER[$b['state']], $a['hours']])
            ->values();

        $byState = array_fill_keys([ReferralSla::OK, ReferralSla::WARNING, ReferralSla::BREACHED], 0);

        foreach ($open as $row) {
            $byState[$row['state']]++;
        }

        return [
            'count' => $open->count(),
            'by_state' => $byState,
            'goal_hours' => ReferralSla::HOURS,
            'top' => $open->take(self::LIST_SIZE)->map(fn (array $row) => [
                'id' => $row['referral']->id,
                'lead_id' => $row['referral']->lead_id,
                'company' => $row['referral']->lead->company,
                'partner' => ['id' => $row['referral']->partner->id, 'name' => $row['referral']->partner->name],
                'state' => $row['state'],
                'hours' => $row['hours'],
            ])->all(),
            'more' => max(0, $open->count() - self::LIST_SIZE),
        ];
    }

    /**
     * @param  iterable<Lead>  $leads
     * @return array{count: int, range_cents: array{0: int, 1: int}|null, stages: list<array{stage: string, count: int, range_cents: array{0: int, 1: int}|null}>}
     */
    private function pipeline(iterable $leads, FeeQuartiles $fees): array
    {
        $bands = array_fill_keys(array_map(fn (LeadStage $stage) => $stage->value, self::OPEN_STAGES), []);

        foreach ($leads as $lead) {
            if (isset($bands[$lead->stage])) {
                $band = $lead->profile['band'] ?? null;
                $bands[$lead->stage][] = is_string($band) ? $band : null;
            }
        }

        $stages = [];

        foreach ($bands as $stage => $stageBands) {
            $stages[] = ['stage' => $stage, 'count' => count($stageBands), 'range_cents' => $fees->annualRange($stageBands)];
        }

        return [
            'count' => array_sum(array_column($stages, 'count')),
            'range_cents' => $fees->annualRange(array_merge(...array_values($bands))),
            'stages' => $stages,
        ];
    }
}
