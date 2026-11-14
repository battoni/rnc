<?php

namespace Database\Seeders;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Partner\Enums\PartnerSpecialty;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Helpers\BusinessHours;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\PartnerMatcher;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\DemoEnvironment;
use Database\Seeders\Concerns\SeedsEachDemoFirm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The network to show (rnc-1 F6, R21), outside production only: the demo's eight invented partners,
 * one per specialty of facts.md §3, each marked "(exemplo)" with an invented e-mail
 * (`@parceiro.exemplo`) and phone (`55 11 9000-9NNN`) — placeholders until Franco names its own.
 * Then the demo's dozen referrals (rnc-2 F2, `REFERRAL_PLAN`), each for a seeded lead that SAID YES,
 * to a partner that covers its diagnostic, with the consent copied: three still `enviado` (3, 15 and
 * 30 business hours old — the SLA chip's three colours), the rest walked along the graph with one
 * `encaminhamento.status` line per step. The seeded moves carry no login (`status_by`, the line's
 * `user_id`): the partners' logins are seeded after (`PartnerUserSeeder`).
 *
 * Deterministic, after `PipelineSeeder` (it needs the leads). Runs inside `CurrentFirm::runAs` and
 * writes `firm_id` itself — `DatabaseSeeder` runs without model events (state.md Pitfalls).
 */
class PartnerSeeder extends Seeder
{
    use SeedsEachDemoFirm;

    /** @var list<array{0: string, 1: PartnerSpecialty, 2: string}> name, specialty, city */
    private const PARTNERS = [
        ['Andrade Tributário (exemplo)', PartnerSpecialty::AdvocaciaTributaria, 'São Paulo-SP'],
        ['Recupera Créditos (exemplo)', PartnerSpecialty::RecuperacaoCreditos, 'São Paulo-SP'],
        ['NexoERP (exemplo)', PartnerSpecialty::ErpNfe, 'Barueri-SP'],
        ['Giro Capital (exemplo)', PartnerSpecialty::CreditoCapitalGiro, 'São Paulo-SP'],
        ['Amparo Seguros (exemplo)', PartnerSpecialty::SegurosBeneficios, 'Osasco-SP'],
        ['Herança Societário (exemplo)', PartnerSpecialty::SocietarioSucessao, 'São Paulo-SP'],
        ['Margem Certa (exemplo)', PartnerSpecialty::PrecificacaoControladoria, 'Santo André-SP'],
        ['CertiDigital (exemplo)', PartnerSpecialty::CertificadoDigital, 'São Paulo-SP'],
    ];

    private const PHONE_PREFIX = '551190009';

    /** The stages whose leads have a diagnostic and a conversation worth handing over. */
    private const REFERABLE_STAGES = ['lead', 'reuniao_marcada', 'proposta', 'cliente'];

    /** The demo's dozen (`REFERRAL_PLAN`): every status, three still waiting for the first call. */
    private const REFERRAL_PLAN = ['fechado', 'contato', 'enviado', 'proposta', 'fechado', 'enviado', 'reuniao', 'aceito', 'fechado', 'recusado', 'enviado', 'fechado'];

    /** How many business hours ago the three open ones were sent: inside the SLA, in amber, breached. */
    private const OPEN_AGES = [3, 15, 30];

    /** The others were sent at least this many days ago, so their walk is behind them. */
    private const SETTLED_DAYS = 5;

    /** The demo's `dealHint`: a first-year contract value by revenue band, in cents. */
    private const DEAL_BY_BAND = ['b1' => 600_000, 'b2' => 1_200_000, 'b3' => 2_400_000, 'b4' => 4_800_000, 'b5' => 9_600_000];

    private const DEAL_DEFAULT_CENTS = 1_200_000;

    /** Who sends them — the team that works the board. */
    private const TEAM_SIZE = 3;

    private const MESSAGE = 'A %s indica a %s. Podem fazer o primeiro contato em até 24 horas úteis? Nós apresentamos vocês.';

    public function run(CurrentFirm $currentFirm, PartnerMatcher $partnerMatcher): void
    {
        if (! DemoEnvironment::allows()) {
            return;
        }

        $this->forEachDemoFirm($currentFirm, fn (Firm $firm) => $this->seedFirm($firm, $partnerMatcher), FirmModules::PARTNERS);
    }

    private function seedFirm(Firm $firm, PartnerMatcher $partnerMatcher): void
    {
        $partners = new Collection;

        foreach (self::PARTNERS as $index => [$name, $specialty, $city]) {
            $partners->push(Partner::query()->create([
                'firm_id' => $firm->id,
                'name' => $name,
                'specialty' => $specialty->value,
                'email' => 'contato'.($index + 1).'@parceiro.exemplo',
                'phone' => self::PHONE_PREFIX.sprintf('%03d', $index + 1),
                'city' => $city,
                'active' => true,
            ]));
        }

        $this->seedReferrals($firm, $partners, $partnerMatcher);
    }

    /** @param  Collection<int, Partner>  $partners */
    private function seedReferrals(Firm $firm, Collection $partners, PartnerMatcher $partnerMatcher): void
    {
        $team = $this->teamOf($firm, self::TEAM_SIZE);

        if ($team->isEmpty()) {
            return;
        }

        $leads = Lead::query()
            ->with('latestConsent')
            ->whereIn('stage', self::REFERABLE_STAGES)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Lead $lead) => $lead->latestConsent?->given === true)
            ->values()
            ->take(count(self::REFERRAL_PLAN));
        $now = CarbonImmutable::now();
        $week = new BusinessHours($firm->business_hours ?? []);
        $openAges = self::OPEN_AGES;

        foreach ($leads as $index => $lead) {
            $status = ReferralStatus::from(self::REFERRAL_PLAN[$index]);
            $matches = $partnerMatcher->match($lead, $partners);
            $covering = array_values(array_filter($matches, fn (array $match) => $match['covers'] !== []));
            $match = $covering === [] ? $matches[0] : $covering[$index % count($covering)];
            $member = $team[$index % $team->count()];
            $consent = $lead->latestConsent;
            $at = $status === ReferralStatus::Enviado
                ? $week->before($now, (float) array_shift($openAges))
                : $this->sentAt($lead, $now);
            $walk = $this->walk($lead, $status, $at, $now, $week, $index);

            $referral = Referral::query()->create([
                'firm_id' => $firm->id,
                'lead_id' => $lead->id,
                'partner_id' => $match['partner']->id,
                'referred_by' => $member->id,
                'message' => sprintf(self::MESSAGE, $firm->short_name ?? $firm->name, $lead->company),
                'score' => $match['score'],
                'covers' => $match['covers'],
                'consent_text' => $consent->text,
                'consent_version' => $consent->version,
                'consent_given_at' => $consent->given_at,
                'status' => $status->value,
                ...$walk['columns'],
                'created_at' => $at,
                'updated_at' => $walk['columns']['status_at'] ?? $at,
            ]);

            $line = ['referral_id' => $referral->id, 'partner_id' => $match['partner']->id, 'partner' => $match['partner']->name];

            LeadEvent::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $member->id,
                'type' => LeadEventType::ReferralSent->value,
                'data' => [...$line, 'specialty' => $match['partner']->specialty, 'score' => $match['score']],
                'at' => $at,
            ]);

            foreach ($walk['steps'] as [$from, $to, $stepAt, $extra]) {
                LeadEvent::query()->create([
                    'lead_id' => $lead->id,
                    'user_id' => null,
                    'type' => LeadEventType::ReferralStatusChanged->value,
                    'data' => [...$line, 'from' => $from, 'to' => $to, ...$extra],
                    'at' => $stepAt,
                ]);
            }
        }
    }

    /**
     * A moved referral's send date: a day after the lead's last touch, at least five days ago, never
     * before the lead itself.
     */
    private function sentAt(Lead $lead, CarbonImmutable $now): CarbonImmutable
    {
        $touched = CarbonImmutable::parse($lead->updated_at);

        return $touched->addDay()->min($now->subDays(self::SETTLED_DAYS))->max(CarbonImmutable::parse($lead->created_at)->addHour());
    }

    /**
     * Where the partner took the referral, step by step along the graph (the demo's
     * `REFERRAL_PLAN`): the first contact 2–7 business hours after the send (inside the SLA), the
     * last step 1–4 days after it and after the contact (never later than an hour ago), the steps
     * between spread evenly; a meeting still ahead at 14:00
     * on a weekday, one already held at the proposal's moment; the band's contract value.
     * Deterministic by the referral's place in the plan.
     *
     * @return array{columns: array<string, mixed>, steps: list<array{0: string, 1: string, 2: CarbonImmutable, 3: array<string, mixed>}>}
     */
    private function walk(Lead $lead, ReferralStatus $status, CarbonImmutable $at, CarbonImmutable $now, BusinessHours $week, int $index): array
    {
        if ($status === ReferralStatus::Enviado) {
            return ['columns' => [], 'steps' => []];
        }

        $path = $this->pathTo($status);
        $statusAt = $at->addDays(1 + $index % 4)->min($now->subHour());
        $contactedAt = in_array(ReferralStatus::Contato, $path, true) ? $week->after($at, 2 + $index % 6) : null;
        $statusAt = match (true) {
            $contactedAt === null => $statusAt,
            $status === ReferralStatus::Contato => $contactedAt,
            default => $statusAt->max($contactedAt->addHour())->min($now->subHour()),
        };
        $contactedAt = $contactedAt?->min($statusAt);
        $columns = ['status_at' => $statusAt, 'contacted_at' => $contactedAt];
        $later = array_values(array_filter($path, fn (ReferralStatus $step) => ! in_array($step, [ReferralStatus::Aceito, ReferralStatus::Contato, ReferralStatus::Recusado], true)));
        $moments = [];

        foreach ($path as $step) {
            $moments[$step->value] = match (true) {
                $step === $status => $statusAt,
                $step === ReferralStatus::Aceito => $at->addHour()->min($contactedAt ?? $statusAt),
                $step === ReferralStatus::Contato => $contactedAt,
                default => $contactedAt->addSeconds(intdiv(($statusAt->getTimestamp() - $contactedAt->getTimestamp()) * (array_search($step, $later, true) + 1), count($later))),
            };
        }

        if ($status === ReferralStatus::Reuniao) {
            $meeting = $now->addDays(2 + $index % 5)->setTime(14, 0);

            while ($meeting->isWeekend()) {
                $meeting = $meeting->addDay();
            }

            $columns['meeting_at'] = $meeting;
        } elseif (isset($moments[ReferralStatus::Proposta->value])) {
            // A meeting already held: the proposal came out of it.
            $columns['meeting_at'] = $moments[ReferralStatus::Proposta->value];
        }

        if ($status === ReferralStatus::Fechado) {
            $columns['deal_value_cents'] = self::DEAL_BY_BAND[$lead->profile['band'] ?? ''] ?? self::DEAL_DEFAULT_CENTS;
            $columns['closed_at'] = $statusAt;
        }

        $steps = [];
        $from = ReferralStatus::Enviado;

        foreach ($path as $step) {
            $extra = match ($step) {
                ReferralStatus::Reuniao => ['meeting_at' => $columns['meeting_at']->utc()->toIso8601String()],
                ReferralStatus::Fechado => ['deal_value_cents' => $columns['deal_value_cents']],
                default => [],
            };
            $steps[] = [$from->value, $step->value, $moments[$step->value], $extra];
            $from = $step;
        }

        return ['columns' => $columns, 'steps' => $steps];
    }

    /** @return list<ReferralStatus> the graph's steps from `enviado` to `$status`, the first excluded */
    private function pathTo(ReferralStatus $status): array
    {
        $path = [];

        for ($step = ReferralStatus::Enviado; $step !== $status; $path[] = $step) {
            $next = $step->allowedTransitions();
            $step = in_array($status, $next, true) ? $status : $next[0];
        }

        return $path;
    }
}
