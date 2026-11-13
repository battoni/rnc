<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Channel\Models\ChannelFirmEvent;
use App\Modules\Firm\Models\Firm;
use Carbon\CarbonImmutable;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The RNC channel's API (rnc-3 R6–R9, D5, D6): the firms of the Rede Nacional de Contabilidade
 * Franco brings to the platform, from the first contact to active, and Franco's 15 % of each active
 * firm's fee.
 *
 *   GET   /api/channel                      the board: summary + one row per firm (`channel.read`)
 *   POST  /api/channel/firms                a new firm, at contato (`channel.manage`)
 *   PATCH /api/channel/firms/{id}           name, city, fee, share, notes (`channel.manage`)
 *   POST  /api/channel/firms/{id}/advance   one stage forward, `{to}` naming it (`channel.manage`)
 *
 * A7: the sums. A8: the moves, their events and who may make them. AM9: the bounds. AM10: the seed
 * is invented. AM15: Reinaldo's board never carries the platform's price.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, ChannelSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->litoral = ChannelFirm::withoutGlobalScopes()->where('name', 'Litoral Assessoria Contábil (exemplo)')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function channelPerson(string $role, ?Firm $firm = null): User
{
    return User::factory()->create([
        'firm_id' => ($firm ?? Firm::where('slug', 'franco')->firstOrFail())->id,
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function channelFirmOf(string $name): ChannelFirm
{
    return ChannelFirm::withoutGlobalScopes()->where('name', $name)->firstOrFail();
}

describe('the seed (R29, AM10)', function () {
    it('seeds the demo\'s six invented firms, three of them active, 5 to 90 days into their stage', function () {
        $firms = ChannelFirm::withoutGlobalScopes()->orderBy('id')->get();
        $now = CarbonImmutable::now();

        expect($firms->pluck('stage')->all())->toBe(['ativo', 'ativo', 'implantacao', 'demo', 'ativo', 'contato'])
            ->and($firms->every(fn (ChannelFirm $firm) => $firm->monthly_fee_cents === 350000 && $firm->share_bp === 1500))->toBeTrue()
            ->and($firms->pluck('firm_id')->unique()->all())->toBe([$this->franco->id])
            ->and($firms->every(fn (ChannelFirm $firm) => ($days = $firm->stage_at->diffInDays($now)) >= 5 && $days <= 90))->toBeTrue();
    });

    it('marks every seeded firm "(exemplo)": no real RNC member is named (AM10)', function () {
        $names = ChannelFirm::withoutGlobalScopes()->pluck('name');

        expect($names)->toHaveCount(6)
            ->and($names->every(fn (string $name) => str_ends_with($name, ' (exemplo)')))->toBeTrue();
    });

    it('rewrites the six rows on a second run instead of adding six more', function () {
        $this->seed(ChannelSeeder::class);

        expect(ChannelFirm::withoutGlobalScopes()->count())->toBe(6);
    });

    it('seeds nothing on production', function () {
        ChannelFirm::withoutGlobalScopes()->delete();
        app()->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` would stop to ask "really, in production?".
        app(ChannelSeeder::class)->setContainer(app())->__invoke();

        expect(ChannelFirm::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('the board (A7)', function () {
    it('sums the three active firms: R$ 525 each, R$ 1.575/mês, R$ 18.900/ano, and R$ 1.575/mês more to come', function () {
        $this->actingAs(channelPerson('operador'))->getJson('/api/channel')
            ->assertOk()
            ->assertJsonPath('data.summary.monthly_share_cents', 157500)
            ->assertJsonPath('data.summary.yearly_share_cents', 1890000)
            ->assertJsonPath('data.summary.pending_monthly_share_cents', 157500)
            ->assertJsonPath('data.summary.counts', ['contato' => 1, 'demo' => 1, 'implantacao' => 1, 'ativo' => 3]);
    });

    it('answers one row per firm with its share, stage and next stage, in the demo\'s order', function () {
        $rows = $this->actingAs(channelPerson('operador'))->getJson('/api/channel')->assertOk()->json('data.firms');

        expect(array_column($rows, 'name'))->toBe([
            'Contábil Serra Azul (exemplo)',
            'Prisma Contabilidade (exemplo)',
            'Litoral Assessoria Contábil (exemplo)',
            'Cerrado Contadores (exemplo)',
            'Pampa Gestão Contábil (exemplo)',
            'Mandacaru Contabilidade (exemplo)',
        ])
            ->and(array_column($rows, 'share_cents'))->toBe([52500, 52500, 52500, 52500, 52500, 52500])
            ->and(array_column($rows, 'next_stage'))->toBe([null, null, 'ativo', 'implantacao', null, 'demo'])
            ->and($rows[2]['city'])->toBe('Santos-SP')
            ->and($rows[2]['stage_at'])->toBe($this->litoral->stage_at->toIso8601String());
    });

    it('rounds each firm\'s share half-up before summing: R$ 3.500,10 at 15 % adds R$ 525,02', function () {
        $operator = channelPerson('operador');
        $this->actingAs($operator)->patchJson("/api/channel/firms/{$this->litoral->id}", ['monthly_fee_cents' => 350010])->assertOk();

        $this->actingAs($operator)->postJson("/api/channel/firms/{$this->litoral->id}/advance", ['to' => 'ativo'])
            ->assertOk()
            ->assertJsonPath('data.firms.2.share_cents', 52502)
            ->assertJsonPath('data.summary.monthly_share_cents', 157500 + 52502)
            ->assertJsonPath('data.summary.yearly_share_cents', (157500 + 52502) * 12);
    });

    it('moves the hero from R$ 1.575 to R$ 2.100/mês when Litoral goes live (plan F3a done-when)', function () {
        $this->actingAs(channelPerson('operador'))->postJson("/api/channel/firms/{$this->litoral->id}/advance", ['to' => 'ativo'])
            ->assertOk()
            ->assertJsonPath('data.summary.monthly_share_cents', 210000)
            ->assertJsonPath('data.summary.pending_monthly_share_cents', 105000)
            ->assertJsonPath('data.summary.counts.ativo', 4);
    });

    it('answers in the same number of queries for 6 firms or 30 (plan P13)', function () {
        $operator = channelPerson('operador');
        $count = function () use ($operator) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($operator)->getJson('/api/channel')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $six = $count();
        ChannelFirm::factory()->count(24)->create(['firm_id' => $this->franco->id]);

        expect($count())->toBe($six);
    });
});

describe('who sees the price (AM15)', function () {
    it('gives Reinaldo his share, stage and year total, never the fee or the share rate', function () {
        $board = $this->actingAs(channelPerson('ceo'))->getJson('/api/channel')->assertOk()->json('data');

        expect($board)->not->toHaveKey('defaults')
            ->and($board['summary']['monthly_share_cents'])->toBe(157500)
            ->and($board['summary']['yearly_share_cents'])->toBe(1890000);

        foreach ($board['firms'] as $row) {
            expect($row)->toHaveKeys(['name', 'city', 'stage', 'stage_at', 'share_cents'])
                ->not->toHaveKey('monthly_fee_cents')
                ->not->toHaveKey('share_bp')
                ->not->toHaveKey('notes');
        }

        expect(json_encode($board))->not->toContain('350000');
    });

    it('gives the operator the fee, the share rate, the notes and the defaults', function () {
        $board = $this->actingAs(channelPerson('operador'))->getJson('/api/channel')->assertOk()->json('data');

        expect($board['defaults'])->toBe(['monthly_fee_cents' => 350000, 'share_bp' => 1500])
            ->and($board['firms'][0])->toHaveKeys(['monthly_fee_cents', 'share_bp', 'notes'])
            ->and($board['firms'][0]['monthly_fee_cents'])->toBe(350000)
            ->and($board['firms'][0]['share_bp'])->toBe(1500);
    });
});

describe('moves (A8)', function () {
    it('walks a new firm forward one stage at a time, one event per move', function () {
        $operator = channelPerson('operador');
        $id = $this->actingAs($operator)->postJson('/api/channel/firms', ['name' => 'Nova Contábil (exemplo)', 'city' => 'Campinas-SP'])
            ->assertCreated()
            ->json('data.firms.6.id');

        foreach (['demo', 'implantacao', 'ativo'] as $i => $to) {
            CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
            $this->actingAs($operator)->postJson("/api/channel/firms/{$id}/advance", ['to' => $to])
                ->assertOk()
                ->assertJsonPath('data.firms.6.stage', $to);

            expect(ChannelFirmEvent::withoutGlobalScopes()->where('channel_firm_id', $id)->count())->toBe($i + 1);
        }

        $events = ChannelFirmEvent::withoutGlobalScopes()->where('channel_firm_id', $id)->orderBy('id')->get();
        $firm = ChannelFirm::withoutGlobalScopes()->findOrFail($id);

        expect($events->map(fn (ChannelFirmEvent $event) => "{$event->from}→{$event->to}")->all())->toBe(['contato→demo', 'demo→implantacao', 'implantacao→ativo'])
            ->and($events->pluck('user_id')->unique()->all())->toBe([$operator->id])
            ->and($events->pluck('firm_id')->unique()->all())->toBe([$this->franco->id])
            ->and($events->last()->at->toIso8601String())->toBe(CarbonImmutable::now()->utc()->toIso8601String())
            ->and($firm->stage_at->equalTo($events->last()->at))->toBeTrue();
    });

    it('refuses a skipped stage, a step back and staying put with channel.invalidTransition', function (string $name, string $to) {
        $firm = channelFirmOf($name);
        $before = $firm->stage;

        $this->actingAs(channelPerson('operador'))->postJson("/api/channel/firms/{$firm->id}/advance", ['to' => $to])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'channel.invalidTransition')
            ->assertJsonPath('code', 'invalid_transition');

        expect($firm->fresh()->stage)->toBe($before)
            ->and(ChannelFirmEvent::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'contato → implantacao (skip)' => ['Mandacaru Contabilidade (exemplo)', 'implantacao'],
        'contato → ativo (skip)' => ['Mandacaru Contabilidade (exemplo)', 'ativo'],
        'implantacao → demo (back)' => ['Litoral Assessoria Contábil (exemplo)', 'demo'],
        'demo → contato (back)' => ['Cerrado Contadores (exemplo)', 'contato'],
        'demo → demo (stay)' => ['Cerrado Contadores (exemplo)', 'demo'],
    ]);

    it('refuses to move an active firm: ativo is terminal', function (string $to) {
        $firm = channelFirmOf('Prisma Contabilidade (exemplo)');

        $this->actingAs(channelPerson('operador'))->postJson("/api/channel/firms/{$firm->id}/advance", ['to' => $to])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_transition');

        expect($firm->fresh()->stage)->toBe('ativo');
    })->with(['contato', 'demo', 'implantacao', 'ativo']);

    it('refuses an advance that names no stage, or one that does not exist', function (array $body) {
        $this->actingAs(channelPerson('operador'))->postJson("/api/channel/firms/{$this->litoral->id}/advance", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    })->with([
        'no to' => [[]],
        'unknown' => [['to' => 'cliente']],
    ]);

    it('never moves a stage through the edit', function () {
        $this->actingAs(channelPerson('operador'))->patchJson("/api/channel/firms/{$this->litoral->id}", ['stage' => 'ativo', 'notes' => 'Implantação na semana que vem.'])
            ->assertOk();

        expect($this->litoral->fresh()->stage)->toBe('implantacao')
            ->and($this->litoral->fresh()->notes)->toBe('Implantação na semana que vem.')
            ->and(ChannelFirmEvent::withoutGlobalScopes()->count())->toBe(0);
    });

    it('answers 404 channel.notFound on firm B\'s channel firm and moves nothing', function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = ChannelFirm::factory()->create(['firm_id' => $firmB->id, 'stage' => 'implantacao']);
        $operator = channelPerson('operador');

        $this->actingAs($operator)->postJson("/api/channel/firms/{$theirs->id}/advance", ['to' => 'ativo'])
            ->assertNotFound()
            ->assertJsonPath('error', 'channel.notFound');
        $this->actingAs($operator)->patchJson("/api/channel/firms/{$theirs->id}", ['name' => 'Roubada'])->assertNotFound();

        $names = collect($this->actingAs($operator)->getJson('/api/channel')->json('data.firms'))->pluck('name');

        expect($theirs->fresh()->stage)->toBe('implantacao')
            ->and($names)->not->toContain($theirs->name)
            ->and(ChannelFirmEvent::withoutGlobalScopes()->count())->toBe(0);
    });

    it('gives Reinaldo 200 on the read and 403 on every write', function () {
        $ceo = channelPerson('ceo');

        $this->actingAs($ceo)->getJson('/api/channel')->assertOk();
        $this->actingAs($ceo)->postJson('/api/channel/firms', ['name' => 'Nova (exemplo)', 'city' => 'Campinas-SP'])->assertForbidden();
        $this->actingAs($ceo)->patchJson("/api/channel/firms/{$this->litoral->id}", ['notes' => 'x'])->assertForbidden();
        $this->actingAs($ceo)->postJson("/api/channel/firms/{$this->litoral->id}/advance", ['to' => 'ativo'])->assertForbidden();

        expect($this->litoral->fresh()->stage)->toBe('implantacao')
            ->and(ChannelFirm::withoutGlobalScopes()->count())->toBe(6);
    });

    it('refuses the team on the read and the writes', function () {
        $team = channelPerson('equipe');

        $this->actingAs($team)->getJson('/api/channel')->assertForbidden();
        $this->actingAs($team)->postJson("/api/channel/firms/{$this->litoral->id}/advance", ['to' => 'ativo'])->assertForbidden();
    });
});

describe('add and edit (R7, AM9)', function () {
    it('adds a firm at contato with the defaults from config/channel.php, on the current firm', function () {
        $this->actingAs(channelPerson('operador'))->postJson('/api/channel/firms', ['name' => '  Nova Contábil (exemplo) ', 'city' => 'Campinas-SP'])
            ->assertCreated()
            ->assertJsonPath('data.firms.6.name', 'Nova Contábil (exemplo)')
            ->assertJsonPath('data.firms.6.stage', 'contato')
            ->assertJsonPath('data.firms.6.monthly_fee_cents', 350000)
            ->assertJsonPath('data.firms.6.share_bp', 1500)
            ->assertJsonPath('data.summary.counts.contato', 2);

        $firm = channelFirmOf('Nova Contábil (exemplo)');

        expect($firm->firm_id)->toBe($this->franco->id)
            ->and($firm->stage_at->toIso8601String())->toBe(CarbonImmutable::now()->utc()->toIso8601String());
    });

    it('takes a fee and a share of its own, and a stage only through the advance', function () {
        $this->actingAs(channelPerson('operador'))->postJson('/api/channel/firms', [
            'name' => 'Nova Contábil (exemplo)', 'city' => 'Campinas-SP', 'monthly_fee_cents' => 420000, 'share_bp' => 2000, 'stage' => 'ativo', 'notes' => 'Indicada pela Prisma.',
        ])->assertCreated();

        $firm = channelFirmOf('Nova Contábil (exemplo)');

        expect([$firm->monthly_fee_cents, $firm->share_bp, $firm->stage, $firm->notes])->toBe([420000, 2000, 'contato', 'Indicada pela Prisma.']);
    });

    it('edits the fee, the share and the notes, and clears the notes with null', function () {
        $operator = channelPerson('operador');
        $this->actingAs($operator)->patchJson("/api/channel/firms/{$this->litoral->id}", ['monthly_fee_cents' => 300000, 'share_bp' => 1000, 'notes' => 'Desconto de fundador.'])
            ->assertOk()
            ->assertJsonPath('data.firms.2.monthly_fee_cents', 300000)
            ->assertJsonPath('data.firms.2.share_cents', 30000);

        $this->actingAs($operator)->patchJson("/api/channel/firms/{$this->litoral->id}", ['notes' => null])->assertOk();

        expect($this->litoral->fresh()->notes)->toBeNull()
            ->and($this->litoral->fresh()->monthly_fee_cents)->toBe(300000);
    });

    it('accepts the bounds: fee R$ 0,01 to R$ 100.000,00, share 0 to 50 %', function (array $body) {
        $this->actingAs(channelPerson('operador'))->patchJson("/api/channel/firms/{$this->litoral->id}", $body)->assertOk();
    })->with([
        'fee 1' => [['monthly_fee_cents' => 1]],
        'fee 10.000.000' => [['monthly_fee_cents' => 10_000_000]],
        'share 0' => [['share_bp' => 0]],
        'share 5000' => [['share_bp' => 5000]],
    ]);

    it('refuses a fee or a share out of bounds, or not in whole cents and points (AM9)', function (string $method, array $body, string $field) {
        $uri = $method === 'POST' ? '/api/channel/firms' : "/api/channel/firms/{$this->litoral->id}";
        $base = $method === 'POST' ? ['name' => 'Nova (exemplo)', 'city' => 'Campinas-SP'] : [];

        $this->actingAs(channelPerson('operador'))->json($method, $uri, [...$base, ...$body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(ChannelFirm::withoutGlobalScopes()->count())->toBe(6)
            ->and($this->litoral->fresh()->monthly_fee_cents)->toBe(350000);
    })->with([
        'POST fee 0' => ['POST', ['monthly_fee_cents' => 0], 'monthly_fee_cents'],
        'POST fee 10.000.001' => ['POST', ['monthly_fee_cents' => 10_000_001], 'monthly_fee_cents'],
        'POST share -1' => ['POST', ['share_bp' => -1], 'share_bp'],
        'POST share 5001' => ['POST', ['share_bp' => 5001], 'share_bp'],
        'PATCH fee 0' => ['PATCH', ['monthly_fee_cents' => 0], 'monthly_fee_cents'],
        'PATCH fee 10.000.001' => ['PATCH', ['monthly_fee_cents' => 10_000_001], 'monthly_fee_cents'],
        'PATCH fee in reais' => ['PATCH', ['monthly_fee_cents' => 3500.5], 'monthly_fee_cents'],
        'PATCH share 5001' => ['PATCH', ['share_bp' => 5001], 'share_bp'],
        'PATCH fee null' => ['PATCH', ['monthly_fee_cents' => null], 'monthly_fee_cents'],
    ]);

    it('caps the name at 120, the city at 80 and the notes at 500, and needs a name and a city', function (array $body, string $field) {
        $this->actingAs(channelPerson('operador'))->postJson('/api/channel/firms', [...['name' => 'Nova (exemplo)', 'city' => 'Campinas-SP'], ...$body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'name 121' => [['name' => str_repeat('a', 121)], 'name'],
        'city 81' => [['city' => str_repeat('a', 81)], 'city'],
        'notes 501' => [['notes' => str_repeat('a', 501)], 'notes'],
        'no name' => [['name' => ''], 'name'],
        'no city' => [['city' => ''], 'city'],
    ]);
});
