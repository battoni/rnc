<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectorPageSeeder;
use Illuminate\Support\Str;

/*
 * The funnel (rnc-1 F7, R24, A4; plan AM2, AM8, AM11):
 *
 *   GET /api/metrics/funnel?days=7|30   behind `metrics.read` (ceo, operador) — equipe gets 403
 *
 * Seven steps — visitas → clique CTA → início → contato → resultado → agendou → cliente — counted
 * over a cohort: a step counts the visitors of the step before who also did it, so a step is never
 * above the one before. Contato is a `diag.contact` beacon or the visitor's lead reaching lead
 * parcial; cliente is the visitor's lead moved to `cliente` by the team inside the window.
 */
const FUNNEL_STEPS = ['visitas', 'cta', 'inicio', 'contato', 'resultado', 'agendou', 'cliente'];

const FUNNEL_WALK = ['visit', 'cta', 'diag.start', 'diag.contact', 'diag.result', 'booking'];

function metricsPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

/** A visitor of `$firm` with the given beacons, each `$minutesApart` after the last, from `$at`. */
function metricsVisitor(Firm $firm, array $types, CarbonImmutable $at, array $overrides = []): Visitor
{
    $visitor = Visitor::withoutGlobalScopes()->create([
        'firm_id' => $firm->id,
        'uuid' => (string) Str::uuid(),
        'source' => $overrides['source'] ?? 'organico',
        'medium' => null,
        'first_seen_at' => $at,
        'last_seen_at' => $at,
    ]);

    foreach ($types as $position => $type) {
        TrackingEvent::withoutGlobalScopes()->create([
            'firm_id' => $firm->id,
            'visitor_id' => $visitor->id,
            'type' => $type,
            'path' => $position === 0 ? ($overrides['path'] ?? '/') : '/diagnostico',
            'sector' => $overrides['sector'] ?? null,
            'source' => $visitor->source,
            'medium' => null,
            'lead_id' => $overrides['lead_id'] ?? null,
            'at' => $at->addMinutes($position),
        ]);
    }

    return $visitor;
}

function funnelOf(User $person, int $days = 30): array
{
    return test()->actingAs($person)->getJson("/api/metrics/funnel?days={$days}")->assertOk()->json('data');
}

/** @return array<string, int> step key → count */
function stepCounts(array $funnel): array
{
    return array_column($funnel['steps'], 'count', 'key');
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SectorPageSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->ceo = metricsPerson('ceo');
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('who reads the funnel (AM8, A2)', function () {
    it('opens for the ceo and the operador', function (string $role) {
        $this->actingAs(metricsPerson($role))->getJson('/api/metrics/funnel?days=7')->assertOk();
    })->with(['ceo', 'operador']);

    it('refuses the team with a 403', function () {
        $this->actingAs(metricsPerson('equipe'))->getJson('/api/metrics/funnel?days=7')->assertForbidden();
    });

    it('refuses a guest', function () {
        $this->getJson('/api/metrics/funnel?days=7')->assertUnauthorized();
    });
});

describe('the window', function () {
    it('takes 7 or 30 days and nothing else', function (mixed $days) {
        $this->actingAs($this->ceo)->getJson("/api/metrics/funnel?days={$days}")->assertUnprocessable()->assertJsonValidationErrors('days');
    })->with([14, 0, 'abc']);

    it('reads the last 30 days when no window is asked', function () {
        expect($this->actingAs($this->ceo)->getJson('/api/metrics/funnel')->assertOk()->json('data.days'))->toBe(30);
    });

    it('counts a visit of ten days ago in 30 days and not in 7', function () {
        metricsVisitor($this->franco, ['visit'], CarbonImmutable::now()->subDays(10));
        metricsVisitor($this->franco, ['visit'], CarbonImmutable::now()->subDays(2));
        metricsVisitor($this->franco, ['visit'], CarbonImmutable::now()->subDays(31));

        expect(stepCounts(funnelOf($this->ceo, 7))['visitas'])->toBe(1)
            ->and(stepCounts(funnelOf($this->ceo, 30))['visitas'])->toBe(2);
    });
});

describe('the steps (R24)', function () {
    it('answers the seven steps in order with the drop-off from the step before', function () {
        metricsVisitor($this->franco, ['visit', 'cta'], CarbonImmutable::now()->subDay());
        metricsVisitor($this->franco, ['visit', 'cta'], CarbonImmutable::now()->subDay());
        metricsVisitor($this->franco, ['visit', 'cta', 'diag.start'], CarbonImmutable::now()->subDay());
        metricsVisitor($this->franco, ['visit'], CarbonImmutable::now()->subDay());

        $steps = funnelOf($this->ceo, 7)['steps'];

        expect(array_column($steps, 'key'))->toBe(FUNNEL_STEPS)
            ->and(array_column($steps, 'count'))->toBe([4, 3, 1, 0, 0, 0, 0])
            ->and($steps[0]['drop_off'])->toBeNull()
            ->and($steps[1]['drop_off'])->toEqual(25)
            ->and($steps[2]['drop_off'])->toBe(66.7)
            ->and($steps[4]['drop_off'])->toEqual(0);
    });

    it('counts distinct visitors, not beacons', function () {
        metricsVisitor($this->franco, ['visit', 'visit', 'cta', 'cta', 'visit'], CarbonImmutable::now()->subDay());

        expect(array_slice(array_values(stepCounts(funnelOf($this->ceo, 7))), 0, 2))->toBe([1, 1]);
    });

    it('never counts a step for a visitor who skipped the one before (a cohort funnel)', function () {
        // a CTA beacon without a page view, a diagnostic started without the CTA, a booking without a result
        metricsVisitor($this->franco, ['cta', 'diag.start'], CarbonImmutable::now()->subDay());
        metricsVisitor($this->franco, ['visit', 'diag.start', 'diag.contact'], CarbonImmutable::now()->subDay());
        metricsVisitor($this->franco, ['visit', 'cta', 'diag.start', 'diag.contact', 'booking'], CarbonImmutable::now()->subDay());

        expect(stepCounts(funnelOf($this->ceo, 7)))->toBe([
            'visitas' => 2, 'cta' => 1, 'inicio' => 1, 'contato' => 1, 'resultado' => 0, 'agendou' => 0, 'cliente' => 0,
        ]);
    });

    it('keeps every step at most the one before on a mixed crowd', function () {
        foreach (range(0, 59) as $index) {
            $types = array_values(array_filter(FUNNEL_WALK, fn (string $type, int $position) => ($index >> $position) % 2 === 1 || $position === 0, ARRAY_FILTER_USE_BOTH));
            metricsVisitor($this->franco, $index % 7 === 0 ? array_slice($types, 1) : $types, CarbonImmutable::now()->subHours($index));
        }

        $counts = array_values(stepCounts(funnelOf($this->ceo, 7)));

        foreach (array_slice($counts, 1) as $position => $count) {
            expect($count)->toBeLessThanOrEqual($counts[$position]);
        }

        expect($counts[0])->toBeGreaterThan($counts[2]);
    });

    it('counts a contact from the lead reaching lead parcial when its beacon was lost', function () {
        $lead = Lead::factory()->create(['firm_id' => $this->franco->id]);
        $visitor = metricsVisitor($this->franco, ['visit', 'cta', 'diag.start'], CarbonImmutable::now()->subDay(), ['lead_id' => $lead->id]);
        $lead->forceFill(['visitor_uuid' => $visitor->uuid])->save();
        LeadEvent::query()->create(['lead_id' => $lead->id, 'type' => 'contato.capturado', 'data' => ['consent' => true], 'at' => CarbonImmutable::now()->subDay()->addMinutes(5)]);

        expect(stepCounts(funnelOf($this->ceo, 7))['contato'])->toBe(1);
    });

    it('counts a cliente only when the team moved the lead there inside the window', function () {
        $walk = ['visit', 'cta', 'diag.start', 'diag.contact', 'diag.result', 'booking'];
        $recent = Lead::factory()->create(['firm_id' => $this->franco->id, 'stage' => 'cliente']);
        $old = Lead::factory()->create(['firm_id' => $this->franco->id, 'stage' => 'cliente']);
        metricsVisitor($this->franco, $walk, CarbonImmutable::now()->subDays(5), ['lead_id' => $recent->id]);
        metricsVisitor($this->franco, $walk, CarbonImmutable::now()->subDays(5), ['lead_id' => $old->id]);
        LeadEvent::query()->create(['lead_id' => $recent->id, 'type' => 'etapa.alterada', 'data' => ['from' => 'proposta', 'to' => 'cliente', 'via' => 'botao'], 'at' => CarbonImmutable::now()->subDays(2)]);
        LeadEvent::query()->create(['lead_id' => $old->id, 'type' => 'etapa.alterada', 'data' => ['from' => 'cliente', 'to' => 'proposta', 'via' => 'botao'], 'at' => CarbonImmutable::now()->subDays(2)]);

        expect(stepCounts(funnelOf($this->ceo, 7))['cliente'])->toBe(1);
    });

    it('never counts another firm\'s traffic (R1)', function () {
        metricsVisitor(Firm::factory()->create(), ['visit', 'cta'], CarbonImmutable::now()->subDay());

        expect(stepCounts(funnelOf($this->ceo, 7))['visitas'])->toBe(0);
    });
});

describe('one visitor walking the whole site (A4)', function () {
    it('raises every step by exactly one', function () {
        foreach (range(0, 11) as $index) {
            metricsVisitor($this->franco, array_slice(FUNNEL_WALK, 0, 1 + $index % 6), CarbonImmutable::now()->subDays($index % 6));
        }

        $before = stepCounts(funnelOf($this->ceo, 7));
        $visitor = (string) Str::uuid();
        $beacon = fn (string $type, ?string $token = null) => $this->postJson('/api/public/events', array_filter([
            'visitor' => $visitor, 'type' => $type, 'path' => $type === 'visit' ? '/reforma/saude' : '/diagnostico', 'sector' => 'saude', 'source' => 'pago', 'medium' => 'cpc', 'lead_token' => $token,
        ]))->assertCreated();

        $beacon('visit');
        $beacon('cta');
        $token = $this->postJson('/api/public/diagnostics', ['path' => 'quiz', 'visitor' => $visitor, 'source' => 'pago', 'sector' => 'saude'])->assertCreated()->json('data.token');

        foreach (['diag.start', 'diag.contact', 'diag.result', 'booking'] as $type) {
            $beacon($type, $token);
        }

        $lead = Lead::withoutGlobalScopes()->where('token', $token)->sole();
        $this->actingAs(metricsPerson('equipe'))->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente', 'via' => 'botao'])->assertOk();

        $after = stepCounts(funnelOf($this->ceo, 7));

        foreach (FUNNEL_STEPS as $step) {
            expect($after[$step])->toBe($before[$step] + 1, "step {$step}");
        }
    });
});

describe('the split by source and by sector page', function () {
    it('counts each source by the visitor\'s first touch, all four always listed', function () {
        metricsVisitor($this->franco, ['visit', 'cta', 'diag.start', 'diag.contact'], CarbonImmutable::now()->subDay(), ['source' => 'pago']);
        metricsVisitor($this->franco, ['visit'], CarbonImmutable::now()->subDay(), ['source' => 'pago']);
        metricsVisitor($this->franco, ['visit', 'cta'], CarbonImmutable::now()->subDay(), ['source' => 'parceiro']);

        $rows = collect(funnelOf($this->ceo, 7)['by_source'])->keyBy('source');

        expect($rows->keys()->all())->toBe(['organico', 'pago', 'parceiro', 'direto'])
            ->and($rows['pago'])->toMatchArray(['visitas' => 2, 'cta' => 1, 'contato' => 1, 'agendou' => 0, 'cliente' => 0])
            ->and($rows['parceiro'])->toMatchArray(['visitas' => 1, 'cta' => 1, 'contato' => 0])
            ->and($rows['organico']['visitas'])->toBe(0);
    });

    it('counts a sector page by the visits that entered on it', function () {
        metricsVisitor($this->franco, ['visit', 'cta', 'diag.start', 'diag.contact', 'diag.result', 'booking'], CarbonImmutable::now()->subDay(), ['path' => '/reforma/saude', 'sector' => 'saude']);
        metricsVisitor($this->franco, ['visit', 'cta'], CarbonImmutable::now()->subDay(), ['path' => '/reforma/saude', 'sector' => 'saude']);
        metricsVisitor($this->franco, ['visit', 'cta'], CarbonImmutable::now()->subDay(), ['path' => '/', 'sector' => null]);

        $rows = collect(funnelOf($this->ceo, 7)['by_sector'])->keyBy('slug');

        expect($rows)->toHaveCount(6)
            ->and($rows['saude'])->toMatchArray(['label' => 'Saúde', 'visitas' => 2, 'cta' => 2, 'contato' => 1, 'agendou' => 1])
            ->and($rows['comercio']['visitas'])->toBe(0)
            ->and($rows->sum('visitas'))->toBe(2);
    });
});
