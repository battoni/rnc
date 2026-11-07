<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Helpers\Holidays;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Services\BookingCalendar;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Enums\LeadStep;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A pipeline to show the team (rnc-1 F5), outside production only: 40 invented companies across
 * every stage, arrived over the last 30 days, each with the timeline its stage implies — the
 * diagnostic's lines, the team's moves and notes with who did them — and the meetings: upcoming for
 * "Reunião marcada", past for the leads already further on. Names, CNPJs, e-mails and phones are
 * invented: phones start `55 11 9000-` and e-mails end in `exemplo.com.br`.
 *
 * Deterministic (no faker): the same board on every `make fresh-seed`, relative to today. Runs
 * inside `CurrentFirm::runAs` and writes `firm_id` itself — `DatabaseSeeder` runs without model
 * events (state.md Pitfalls).
 */
class PipelineSeeder extends Seeder
{
    /** @var array<string, int> how many leads each stage gets — 40 in all */
    private const STAGE_SIZES = [
        'visitante' => 5,
        'lead_parcial' => 8,
        'lead' => 8,
        'reuniao_marcada' => 6,
        'proposta' => 5,
        'cliente' => 4,
        'perdido' => 4,
    ];

    private const COMPANIES = [
        'Padaria Trigo Dourado', 'Clínica Vida Plena', 'Metalúrgica Aço Forte', 'Escola Pequeno Saber',
        'Mercado Bom Preço', 'Agropecuária Campo Verde', 'Consultoria Prisma', 'Laboratório Bioanálise',
        'Indústria de Embalagens Fênix', 'Colégio Horizonte Novo', 'Distribuidora Rota Sul', 'Fazenda Três Rios',
        'Studio Pixel Design', 'Odonto Sorriso Claro', 'Móveis Madeira Nobre', 'Curso Idiomas Ponte',
        'Atacado Central Paulista', 'Cooperativa Grão Forte', 'Agência Faro Digital', 'Clínica Pet Amigo',
        'Têxtil Fio de Ouro', 'Faculdade Saber Livre', 'Farmácia Bem Estar', 'Granja Sol Nascente',
        'Engenharia Base Sólida', 'Instituto Mente Sã', 'Gráfica Papel & Cor', 'Escola de Música Allegro',
        'Loja Casa & Conforto', 'Hortifruti Terra Boa', 'Transportadora Via Rápida', 'Centro Médico Aurora',
        'Plásticos Polimax', 'Creche Primeiros Passos', 'Empório Sabor da Serra', 'Viveiro Mudas do Vale',
        'Contabilidade Parceira Norte', 'Fisioterapia Movimento', 'Autopeças Motor Certo', 'Escola Técnica Futuro',
    ];

    private const FIRST_NAMES = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Fábio', 'Gabriela', 'Henrique', 'Isabela', 'João'];

    private const LAST_NAMES = ['Almeida', 'Barros', 'Cardoso', 'Duarte', 'Esteves', 'Freitas', 'Guimarães', 'Lacerda'];

    private const SECTORS = ['comercio', 'saude', 'industria', 'educacao', 'comercio', 'agro', 'servicos', 'saude'];

    private const REGIMES = ['simples', 'presumido', 'real', 'simples', 'nao_sei'];

    private const BANDS = ['b1', 'b2', 'b2', 'b3', 'b4'];

    private const SOURCES = ['organico', 'pago', 'organico', 'parceiro', 'direto'];

    private const LOST_REASONS = [
        'Fechou com o escritório atual por mais um ano.',
        'Sem orçamento para consultoria em 2026.',
        'Não respondeu depois de três tentativas.',
        'Empresa em encerramento.',
    ];

    private const NOTES = [
        'Ligar na segunda de manhã; falar com o sócio.',
        'Pediu proposta com o reajuste já calculado para 2027.',
        'Interesse no split payment: preocupado com o caixa.',
        'Contador atual não falou da Reforma; ótimo gancho.',
    ];

    /** The team members whose names sign the moves and notes. */
    private const TEAM = ['patricia', 'mariana.toledo', 'carlos.prado'];

    private const HISTORY_DAYS = 29;

    private const MEETING_MINUTES = 30;

    /** Upcoming meetings sit on these local times, one per working day per specialist. */
    private const MEETING_TIMES = ['09:00', '10:30', '14:00', '15:30', '11:00', '16:30'];

    private const EMAIL_DOMAIN = 'exemplo.com.br';

    private const PHONE_PREFIX = '55119000';

    private const TOKEN_LENGTH = 48;

    private const LINK_DAYS = 30;

    /** One lead in eight came through the chat receptionist (~12 %, rnc-2 F5's demo weight). */
    private const CHAT_EVERY = 8;

    private const CHAT_OFFSET = 3;

    public function run(CurrentFirm $currentFirm, RulesEngine $rulesEngine): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $currentFirm->runAs($firm, fn () => $this->seedFirm($firm, $rulesEngine));
    }

    private function seedFirm(Firm $firm, RulesEngine $rulesEngine): void
    {
        $now = CarbonImmutable::now();
        $team = User::query()->whereIn('username', self::TEAM)->orderBy('id')->get()->values();
        $specialists = Specialist::query()->with('user')->where('firm_id', $firm->id)->orderBy('id')->get()->values();
        $upcoming = $this->upcomingSlots($now, max(1, $specialists->count()));
        $index = 0;
        $booked = 0;

        foreach (self::STAGE_SIZES as $stage => $size) {
            for ($position = 0; $position < $size; $position++, $index++) {
                $lead = $this->createLead($firm, LeadStage::from($stage), $index, $now);
                $member = $team->isEmpty() ? null : $team[$index % $team->count()];
                $specialist = $specialists->isEmpty() ? null : $specialists[$index % $specialists->count()];

                $this->writeHistory($lead, $rulesEngine, $member, $specialist, $upcoming[$booked] ?? null, $index, $now);

                if ($stage === LeadStage::ReuniaoMarcada->value) {
                    $booked++;
                }
            }
        }
    }

    private function createLead(Firm $firm, LeadStage $stage, int $index, CarbonImmutable $now): Lead
    {
        $isVisitor = $stage === LeadStage::Visitante;
        $isPartial = $stage === LeadStage::LeadParcial;
        $createdAt = $now->subDays(self::HISTORY_DAYS - ($index * 7) % (self::HISTORY_DAYS + 1))->subMinutes(($index * 37) % 600);
        $company = self::COMPANIES[$index % count(self::COMPANIES)];
        $firstName = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
        $lastName = self::LAST_NAMES[$index % count(self::LAST_NAMES)];
        $profile = [
            'sector' => self::SECTORS[$index % count(self::SECTORS)],
            'regime' => $isVisitor ? null : self::REGIMES[$index % count(self::REGIMES)],
            'band' => $isVisitor || $isPartial ? null : self::BANDS[$index % count(self::BANDS)],
            'b2b' => $isVisitor || $isPartial ? null : [20, 45, 80, 60, 95][$index % 5],
            'incentivos' => $index % 6 === 2,
            'exporta' => $index % 9 === 4,
        ];
        $hasEmail = $index % 3 !== 1;
        $hasPhone = $index % 4 !== 3 || ! $hasEmail;

        $lead = new Lead;
        $lead->forceFill([
            'firm_id' => $firm->id,
            'token' => Str::random(self::TOKEN_LENGTH),
            'token_expires_at' => $now->addDays(self::LINK_DAYS),
            'visitor_uuid' => (string) Str::uuid(),
            'stage' => $stage->value,
            'last_step' => $this->lastStepOf($stage, $index)->value,
            'lost_reason' => $stage === LeadStage::Perdido ? self::LOST_REASONS[$index % count(self::LOST_REASONS)] : null,
            'path' => $index % 2 === 0 ? 'cnpj' : 'quiz',
            'company' => $isVisitor ? null : $company,
            'cnpj' => $isVisitor || $index % 2 !== 0 ? null : $this->inventedCnpj($index),
            'contact_name' => $isVisitor ? null : "{$firstName} {$lastName}",
            'phone' => $isVisitor || ! $hasPhone ? null : self::PHONE_PREFIX.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
            'email' => $isVisitor || ! $hasEmail ? null : Str::slug($firstName).'@'.Str::slug($company).'.'.self::EMAIL_DOMAIN,
            'profile' => $profile,
            'channel' => $this->channelOf($index)->value,
            'source' => self::SOURCES[$index % count(self::SOURCES)],
            'medium' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $lead;
    }

    /**
     * The lines a lead of its stage has lived: the diagnostic's, then the booking, then the team's.
     *
     * @param  array{specialist: int, starts_at: CarbonImmutable}|null  $upcomingSlot
     */
    private function writeHistory(Lead $lead, RulesEngine $rulesEngine, ?User $member, ?Specialist $specialist, ?array $upcomingSlot, int $index, CarbonImmutable $now): void
    {
        $stage = LeadStage::from($lead->stage);
        $at = CarbonImmutable::parse($lead->created_at);
        $last = $at;

        $this->event($lead, LeadEventType::DiagnosticStarted, ['path' => $lead->path], $at);

        if ($stage === LeadStage::Visitante) {
            return;
        }

        $consent = $index % 3 !== 2;
        $last = $at->addMinutes(2);
        LeadConsent::query()->create(['lead_id' => $lead->id, 'text' => ConsentTerms::TEXT, 'version' => ConsentTerms::VERSION, 'given' => $consent, 'via' => LeadChannel::from($lead->channel)->consentVia(), 'given_at' => $last]);
        $this->event($lead, LeadEventType::ContactCaptured, ['consent' => $consent], $last);
        $last = $last->addMinutes(3);
        $this->event($lead, LeadEventType::StepReached, ['step' => LeadStep::Perfil->value], $last);

        if ($stage === LeadStage::LeadParcial) {
            $this->touch($lead, $last);

            return;
        }

        $diagnosis = $rulesEngine->diagnose(DiagnosticProfileDTO::from($lead->profile));
        $last = $last->addMinutes(4);
        $this->event($lead, LeadEventType::ResultSeen, ['opportunities' => $diagnosis['opportunities'], 'risks' => $diagnosis['risks']], $last);

        $meeting = $this->meetingOf($stage, $specialist, $upcomingSlot, $last, $now, $index);

        if ($meeting) {
            $last = $last->addMinutes(5);
            $this->book($lead, $meeting['specialist'], $meeting['starts_at'], $last, $now);
        }

        if ($member && $index % 2 === 0) {
            $last = $this->later($last, $now, 1);
            $this->event($lead, LeadEventType::NoteAdded, ['text' => self::NOTES[$index % count(self::NOTES)]], $last, $member);
        }

        $last = $this->teamMoves($lead, $stage, $member, $last, $now);
        $this->touch($lead, $last);
    }

    /** The moves the team made to get the lead where it is (R18): who, how, and the reason for perdido. */
    private function teamMoves(Lead $lead, LeadStage $stage, ?User $member, CarbonImmutable $last, CarbonImmutable $now): CarbonImmutable
    {
        $path = match ($stage) {
            LeadStage::Proposta => [LeadStage::ReuniaoMarcada, LeadStage::Proposta],
            LeadStage::Cliente => [LeadStage::ReuniaoMarcada, LeadStage::Proposta, LeadStage::Cliente],
            LeadStage::Perdido => [LeadStage::Lead, LeadStage::Perdido],
            default => [],
        };
        $from = $path[0] ?? $stage;

        foreach (array_slice($path, 1) as $step => $to) {
            $last = $this->later($last, $now, 2 + $step);
            $data = ['from' => $from->value, 'to' => $to->value, 'via' => $step % 2 === 0 ? 'arrastar' : 'botao'];

            if ($to === LeadStage::Perdido) {
                $data['reason'] = $lead->lost_reason;
            }

            $this->event($lead, LeadEventType::StageChanged, $data, $last, $member);
            $from = $to;
        }

        return $last;
    }

    /**
     * Upcoming for a booked lead, past for one already further on, none otherwise.
     *
     * @param  array{specialist: int, starts_at: CarbonImmutable}|null  $upcomingSlot
     * @return array{specialist: Specialist|int, starts_at: CarbonImmutable}|null
     */
    private function meetingOf(LeadStage $stage, ?Specialist $specialist, ?array $upcomingSlot, CarbonImmutable $resultAt, CarbonImmutable $now, int $index): ?array
    {
        if ($stage === LeadStage::ReuniaoMarcada && $upcomingSlot) {
            return $upcomingSlot;
        }

        $hadMeeting = in_array($stage, [LeadStage::Proposta, LeadStage::Cliente], true);

        if (! $hadMeeting || ! $specialist) {
            return null;
        }

        $day = $resultAt->setTimezone(BookingCalendar::TIMEZONE)->addDay()->startOfDay();

        while ($day->isWeekend() || Holidays::isHoliday($day)) {
            $day = $day->addDay();
        }

        $start = $day->setTimeFromTimeString(self::MEETING_TIMES[$index % count(self::MEETING_TIMES)]);

        return $start->lessThan($now) ? ['specialist' => $specialist, 'starts_at' => $start] : null;
    }

    private function book(Lead $lead, Specialist|int $specialist, CarbonImmutable $startsAt, CarbonImmutable $at, CarbonImmutable $now): void
    {
        $specialistModel = $specialist instanceof Specialist ? $specialist : Specialist::query()->with('user')->findOrFail($specialist);

        Appointment::query()->create([
            'firm_id' => $lead->firm_id,
            'lead_id' => $lead->id,
            'specialist_id' => $specialistModel->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(self::MEETING_MINUTES),
            'reminded_at' => $startsAt->lessThan($now) ? $startsAt->subDay() : null,
        ]);

        $lead->forceFill([
            'email' => $lead->email ?? Str::slug((string) $lead->contact_name).'@'.Str::slug((string) $lead->company).'.'.self::EMAIL_DOMAIN,
            'report_token' => Str::random(self::TOKEN_LENGTH),
            'report_expires_at' => $now->addDays(self::LINK_DAYS),
        ])->save();

        $this->event($lead, LeadEventType::MeetingBooked, ['specialist' => $specialistModel->displayName(), 'starts_at' => BookingCalendar::localIso($startsAt)], $at);
    }

    /**
     * The next free working-day slots, one per specialist per day, so the agenda's current and next
     * weeks show meetings.
     *
     * @return list<array{specialist: int, starts_at: CarbonImmutable}>
     */
    private function upcomingSlots(CarbonImmutable $now, int $specialistCount): array
    {
        $specialistIds = Specialist::query()->orderBy('id')->pluck('id')->all();
        $slots = [];
        $day = $now->setTimezone(BookingCalendar::TIMEZONE)->startOfDay();
        $size = self::STAGE_SIZES[LeadStage::ReuniaoMarcada->value];

        while (count($slots) < $size && $specialistIds !== []) {
            $day = $day->addDay();

            if ($day->isWeekend() || Holidays::isHoliday($day)) {
                continue;
            }

            $slot = count($slots);
            $slots[] = [
                'specialist' => $specialistIds[$slot % $specialistCount],
                'starts_at' => $day->setTimeFromTimeString(self::MEETING_TIMES[$slot % count(self::MEETING_TIMES)]),
            ];
        }

        return $slots;
    }

    private function channelOf(int $index): LeadChannel
    {
        return $index % self::CHAT_EVERY === self::CHAT_OFFSET ? LeadChannel::Chat : LeadChannel::Formulario;
    }

    private function lastStepOf(LeadStage $stage, int $index): LeadStep
    {
        return match ($stage) {
            LeadStage::Visitante => LeadStep::Empresa,
            LeadStage::LeadParcial => $index % 2 === 0 ? LeadStep::Perfil : LeadStep::Faturamento,
            default => LeadStep::Resultado,
        };
    }

    /** A moment after `$after`, `$days` later at most, never past now. */
    private function later(CarbonImmutable $after, CarbonImmutable $now, int $days): CarbonImmutable
    {
        $next = $after->addDays($days)->addHours(2);

        return $next->greaterThan($now) ? $after->addMinutes(30 * $days)->min($now) : $next;
    }

    /** @param  array<string, mixed>  $data */
    private function event(Lead $lead, LeadEventType $type, array $data, CarbonImmutable $at, ?User $by = null): void
    {
        LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $by?->id, 'type' => $type->value, 'data' => $data, 'at' => $at]);
    }

    private function touch(Lead $lead, CarbonImmutable $at): void
    {
        $lead->forceFill(['updated_at' => $at])->save();
    }

    /** A CNPJ with valid check digits on an invented root `90.0NN.NNN/0001`. */
    private function inventedCnpj(int $index): string
    {
        $base = '90'.str_pad((string) (($index + 1) * 1371), 6, '0', STR_PAD_LEFT).'0001';

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;

            foreach ($weights as $position => $weight) {
                $sum += (int) $base[$position] * $weight;
            }

            $rest = $sum % 11;
            $base .= $rest < 2 ? '0' : (string) (11 - $rest);
        }

        return $base;
    }
}
