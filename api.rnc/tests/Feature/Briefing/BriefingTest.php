<?php

use App\Models\User;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Support\Facades\DB;

/*
 * A21 (rnc-3 R23, R24): `GET /api/briefing` (`briefing.read`) is Reinaldo's day in one payload —
 * the new leads of 7 days and of 24 hours, the meetings of the next 5 business days and of today,
 * the referrals still owed a first contact by SLA state, the open pipeline (lead · reuniao_marcada ·
 * proposta) with its annual fee range from the portfolio's quartiles, and the day's actions. A
 * portfolio (`carteira`) lead counts in none of the lead, meeting or pipeline figures.
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo. Friday 20/11 is a national holiday, so the
 * 5 business days are Thu 19, Mon 23, Tue 24, Wed 25 and Thu 26: the window ends 27/11 00:00.
 */
const BRIEFING_ZONE = 'America/Sao_Paulo';

function briefingAt(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, BRIEFING_ZONE);
}

/** @param  array<string, mixed>  $attributes */
function briefingLead(Firm $firm, array $attributes = []): Lead
{
    return Lead::factory()->create([
        'firm_id' => $firm->id,
        'stage' => 'cliente',
        'created_at' => briefingAt('2026-10-01 09:00'),
        ...$attributes,
    ]);
}

function briefingMeeting(Firm $firm, string $company, string $localStart, array $lead = []): Appointment
{
    $start = briefingAt($localStart);
    $specialists = Specialist::withoutGlobalScopes()->where('firm_id', $firm->id)->orderBy('id')->get();
    $taken = Appointment::withoutGlobalScopes()->where('starts_at', $start->utc()->format('Y-m-d H:i:s'))->count();

    return Appointment::withoutGlobalScopes()->create([
        'firm_id' => $firm->id,
        'lead_id' => briefingLead($firm, ['stage' => 'reuniao_marcada', 'company' => $company, ...$lead])->id,
        'specialist_id' => $specialists[$taken]->id,
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
    ]);
}

function briefingPartner(Firm $firm, string $name): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => $firm->id, 'name' => $name, 'specialty' => 'advocacia_tributaria',
        'email' => str($name)->slug().'@parceiro.exemplo', 'active' => true,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function briefingReferral(Firm $firm, Partner $partner, string $company, string $localSent, array $attributes = []): Referral
{
    return Referral::withoutGlobalScopes()->forceCreate([
        'firm_id' => $firm->id,
        'lead_id' => briefingLead($firm, ['company' => $company])->id,
        'partner_id' => $partner->id,
        'referred_by' => User::where('username', 'reinaldo')->value('id'),
        'message' => 'Indicação de teste.',
        'score' => 80,
        'covers' => [],
        'consent_text' => 'x',
        'consent_version' => 1,
        'consent_given_at' => briefingAt($localSent),
        'status' => 'enviado',
        'created_at' => briefingAt($localSent),
        'updated_at' => briefingAt($localSent),
        ...$attributes,
    ]);
}

function briefingClient(Firm $firm, string $band, string $monthlyFee): Client
{
    static $sequence = 0;
    $sequence++;

    return Client::withoutGlobalScopes()->create([
        'firm_id' => $firm->id, 'name' => "Cliente {$sequence}", 'cnpj' => str_pad((string) $sequence, 14, '0', STR_PAD_LEFT),
        'sector' => 'servicos', 'regime' => 'presumido', 'band' => $band, 'b2b_share' => 50, 'monthly_fee' => $monthlyFee,
    ]);
}

function briefingProfile(?string $band): array
{
    return ['sector' => 'servicos', 'regime' => 'presumido', 'band' => $band, 'b2b' => null, 'incentivos' => false, 'exporta' => false];
}

function briefingOf(): array
{
    return test()->actingAs(User::where('username', 'reinaldo')->firstOrFail())->getJson('/api/briefing')->assertOk()->json('data');
}

beforeEach(function () {
    CarbonImmutable::setTestNow(briefingAt('2026-11-19 10:00'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('GET briefing (A21)', function () {
    it('counts the leads of 7 days and of 24 hours that left a contact, never a carteira lead', function () {
        briefingLead($this->franco, ['stage' => 'lead', 'created_at' => briefingAt('2026-11-19 08:00')]);
        briefingLead($this->franco, ['stage' => 'lead_parcial', 'email' => null, 'created_at' => briefingAt('2026-11-18 11:00')]);
        briefingLead($this->franco, ['stage' => 'lead', 'created_at' => briefingAt('2026-11-16 09:00')]);
        briefingLead($this->franco, ['stage' => 'lead', 'created_at' => briefingAt('2026-11-12 09:00')]); // 7 days and 1 hour: out
        briefingLead($this->franco, ['stage' => 'visitante', 'phone' => null, 'email' => null, 'created_at' => briefingAt('2026-11-19 09:00')]);
        briefingLead($this->franco, ['stage' => 'lead', 'channel' => 'carteira', 'created_at' => briefingAt('2026-11-19 09:30')]);

        expect(briefingOf()['leads'])->toBe(['last_7_days' => 3, 'last_24_hours' => 2]);
    });

    it('counts the meetings of the next 5 business days, skipping the holiday, and today’s', function () {
        briefingMeeting($this->franco, 'Ontem', '2026-11-18 15:00');
        briefingMeeting($this->franco, 'Hoje cedo', '2026-11-19 08:00');
        briefingMeeting($this->franco, 'Hoje à tarde', '2026-11-19 14:00');
        briefingMeeting($this->franco, 'Segunda', '2026-11-23 09:00');
        briefingMeeting($this->franco, 'Quinta que vem', '2026-11-26 16:00');
        briefingMeeting($this->franco, 'Sexta 27', '2026-11-27 09:00');
        briefingMeeting($this->franco, 'Perdido', '2026-11-24 10:00', ['stage' => 'perdido']);
        briefingMeeting($this->franco, 'Da carteira', '2026-11-24 11:00', ['channel' => 'carteira']);

        $meetings = briefingOf()['meetings'];

        expect($meetings['count'])->toBe(4)
            ->and($meetings['today'])->toBe(2)
            ->and($meetings['window_end'])->toBe('2026-11-26')
            ->and(array_column($meetings['next'], 'company'))->toBe(['Hoje cedo', 'Hoje à tarde', 'Segunda', 'Quinta que vem'])
            ->and($meetings['next'][1])->toMatchArray(['starts_at' => '2026-11-19T14:00:00-03:00'])
            ->and($meetings['next'][1]['lead_id'])->toBeInt()
            ->and($meetings['next'][1]['specialist'])->toBeString();
    });

    it('lists the referrals owed a first contact by SLA state, breached and longest first, five at most', function () {
        $andrade = briefingPartner($this->franco, 'Andrade Advocacia');
        $lima = briefingPartner($this->franco, 'Lima Créditos');
        briefingReferral($this->franco, $andrade, 'Hoje cedo', '2026-11-19 08:00');          // 2 h: ok
        briefingReferral($this->franco, $lima, 'Terça', '2026-11-17 10:00');                 // 8 + 10 + 2 = 20 h: atenção
        briefingReferral($this->franco, $andrade, 'Segunda', '2026-11-16 08:00');            // 10 + 10 + 10 + 2 = 32 h
        briefingReferral($this->franco, $lima, 'Sexta passada', '2026-11-13 08:00');         // 9 + 32 = 41 h
        briefingReferral($this->franco, $andrade, 'Quarta', '2026-11-18 08:00');             // 12 h: atenção
        briefingReferral($this->franco, $lima, 'Quarta tarde', '2026-11-18 16:00');          // 2 + 2 = 4 h: ok
        briefingReferral($this->franco, $andrade, 'Já ligou', '2026-11-16 08:00', ['status' => 'contato', 'contacted_at' => briefingAt('2026-11-16 15:00')]);
        briefingReferral($this->franco, $lima, 'Recusou', '2026-11-16 08:00', ['status' => 'recusado']);

        $referrals = briefingOf()['referrals'];

        expect($referrals['count'])->toBe(6)
            ->and($referrals['by_state'])->toBe(['ok' => 2, 'atencao' => 2, 'estourado' => 2])
            ->and($referrals['goal_hours'])->toBe(24)
            ->and(array_column($referrals['top'], 'company'))->toBe(['Sexta passada', 'Segunda', 'Terça', 'Quarta', 'Quarta tarde'])
            ->and($referrals['more'])->toBe(1)
            ->and($referrals['top'][0])->toMatchArray(['state' => 'estourado', 'hours' => 41.0, 'partner' => ['id' => $lima->id, 'name' => 'Lima Créditos']]);
    });

    it('sums the open pipeline’s range from the band quartiles × 12, falling back for a band under 5 clients', function () {
        foreach (['1000.00', '2000.00', '3000.00', '4000.00', '5000.00'] as $fee) {
            briefingClient($this->franco, 'b2', $fee);
        }
        briefingClient($this->franco, 'b4', '9000.00');
        briefingClient($this->franco, 'b4', '10000.00');
        briefingLead($this->franco, ['stage' => 'lead', 'profile' => briefingProfile('b2')]);
        briefingLead($this->franco, ['stage' => 'reuniao_marcada', 'profile' => briefingProfile('b4')]);
        briefingLead($this->franco, ['stage' => 'proposta', 'profile' => briefingProfile(null)]);
        briefingLead($this->franco, ['stage' => 'proposta', 'channel' => 'carteira', 'profile' => briefingProfile('b2')]);
        briefingLead($this->franco, ['stage' => 'lead_parcial', 'profile' => briefingProfile('b2')]);
        briefingLead($this->franco, ['stage' => 'perdido', 'profile' => briefingProfile('b2')]);

        $pipeline = briefingOf()['pipeline'];

        // b2 alone → [2 000, 4 000]/mês; b4 (2 clients) and no band → every client's [2 000, 9 000].
        expect($pipeline['count'])->toBe(3)
            ->and($pipeline['range_cents'])->toBe([7_200_000, 26_400_000])
            ->and($pipeline['stages'])->toBe([
                ['stage' => 'lead', 'count' => 1, 'range_cents' => [2_400_000, 4_800_000]],
                ['stage' => 'reuniao_marcada', 'count' => 1, 'range_cents' => [2_400_000, 10_800_000]],
                ['stage' => 'proposta', 'count' => 1, 'range_cents' => [2_400_000, 10_800_000]],
            ]);
    });

    it('gives the pipeline no range on an empty portfolio', function () {
        briefingLead($this->franco, ['stage' => 'proposta', 'profile' => briefingProfile('b2')]);

        $pipeline = briefingOf()['pipeline'];

        expect($pipeline['count'])->toBe(1)
            ->and($pipeline['range_cents'])->toBeNull()
            ->and($pipeline['stages'][2])->toBe(['stage' => 'proposta', 'count' => 1, 'range_cents' => null]);
    });

    it('picks the day’s actions from the same figures: the breached SLA, today’s meetings, the proposals', function () {
        $andrade = briefingPartner($this->franco, 'Andrade Advocacia');
        briefingReferral($this->franco, $andrade, 'Padaria Sol', '2026-11-16 08:00');
        briefingMeeting($this->franco, 'Hoje', '2026-11-19 14:00');
        briefingLead($this->franco, ['stage' => 'proposta']);
        briefingLead($this->franco, ['stage' => 'lead_parcial', 'created_at' => briefingAt('2026-11-19 08:00')]);

        $briefing = briefingOf();

        expect(array_column($briefing['actions'], 'kind'))->toBe(['sla_breached', 'meetings_today', 'proposals'])
            ->and($briefing['actions'][0]['params'])->toEqual(['partner' => 'Andrade Advocacia', 'company' => 'Padaria Sol', 'hours' => 32, 'goal_hours' => 24])
            ->and($briefing['actions'][0]['to'])->toBe("/reinaldo/trilha?parceiro={$andrade->id}")
            ->and($briefing['as_of'])->toBe('2026-11-19T10:00:00-03:00');
    });

    it("adds the Radar's first opportunity type as an action, the same the Radar screen shows first (R25, W81)", function () {
        briefingClient($this->franco, 'b3', '3000.00');
        briefingClient($this->franco, 'b4', '5000.00');
        $reinaldo = User::where('username', 'reinaldo')->firstOrFail();
        $first = collect($this->actingAs($reinaldo)->getJson('/api/radar')->assertOk()->json('data.types'))->firstWhere('kind', 'oportunidade');

        $actions = briefingOf()['actions'];

        expect($first)->not->toBeNull()
            ->and($actions)->toBe([[
                'kind' => 'radar',
                'params' => ['rule' => $first['rule'], 'title' => $first['title'], 'clients' => $first['clients'], 'range_cents' => $first['range_cents'], 'period' => $first['period']],
                'to' => '/reinaldo/radar',
            ]]);
    });

    it('adds no Radar action on an empty portfolio', function () {
        expect(briefingOf()['actions'])->toBe([]);
    });

    it('answers in the same number of queries whatever the number of rows (P13)', function () {
        $andrade = briefingPartner($this->franco, 'Andrade Advocacia');
        briefingClient($this->franco, 'b2', '1000.00');
        $queries = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            briefingOf();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        briefingReferral($this->franco, $andrade, 'Uma', '2026-11-16 08:00');
        briefingMeeting($this->franco, 'Uma reunião', '2026-11-23 09:00');
        $few = $queries();

        foreach (range(1, 6) as $n) {
            briefingReferral($this->franco, briefingPartner($this->franco, "Parceiro {$n}"), "Empresa {$n}", '2026-11-17 10:00');
            briefingMeeting($this->franco, "Reunião {$n}", "2026-11-2{$n} 1{$n}:00");
            briefingLead($this->franco, ['stage' => 'proposta', 'profile' => briefingProfile('b2')]);
        }

        expect($queries())->toBe($few);
    });
});
