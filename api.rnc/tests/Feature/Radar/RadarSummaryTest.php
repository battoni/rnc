<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Fiscal\Services\SplitPaymentCalculator;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\FiscalRows;

/*
 * "Radar da carteira" (rnc-2 F9, R24–R26, A19's Radar Σ, A20; plan AM6), behind `radar.manage`
 * (ceo, operador):
 *
 *   GET /api/radar                {clients, with_opportunity, total_cents, risk_types, types: [row]}
 *   GET /api/radar/types/{rule}   {type: row + detail, clients: [client with range, evidence, keys]}
 *
 * The rules engine runs over EVERY client of the firm. A client with ≥ 10 issued invoices in the
 * last 90 days is read with the B2B share MEASURED from them (by value, to a CNPJ) instead of the
 * declared one (D9). The total adds opportunities only — risks never add money. The split-payment
 * row is Σ of R23's held range per month over the clients (`SplitPaymentCalculator`), from the
 * invoices when the client has any in the window, else from the cadastro.
 */
function radarSummary(): array
{
    return test()->getJson('/api/radar')->assertOk()->json('data');
}

function radarType(string $rule): array
{
    return test()->getJson("/api/radar/types/{$rule}")->assertOk()->json('data');
}

function radarRow(array $summary, string $rule): ?array
{
    return collect($summary['types'])->firstWhere('rule', $rule);
}

function radarClient(array $type, int $id): ?array
{
    return collect($type['clients'])->firstWhere('id', $id);
}

/** `$count` issued invoices of `$cents` each, `$daysAgo` days back, to a company or a consumer. */
function radarSales(Client $client, int $count, int $cents, string $recipient = 'cnpj', int $daysAgo = 10, array $attributes = []): void
{
    foreach (range(1, $count) as $ignored) {
        FiscalRows::document($client, 'saida', [
            'total_cents' => $cents,
            'recipient_kind' => $recipient,
            'recipient_cnpj' => $recipient === 'cnpj' ? '99700000000191' : null,
            'issued_at' => CarbonImmutable::now()->subDays($daysAgo),
            ...$attributes,
        ]);
    }
}

beforeEach(function () {
    Http::preventStrayRequests();
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->ceo = FiscalRows::person('ceo');

    // Declares 20 % B2B, but 12 invoices in 90 days say 80 % by value (10 × R$ 1.000 to a CNPJ,
    // 2 × R$ 1.250 to consumers): read as B2B → simples-regular and simples-credito (A20).
    $this->measured = FiscalRows::client(['name' => 'A Medida Ltda', 'regime' => 'simples', 'sector' => 'servicos', 'band' => 'b2', 'b2b_share' => 20, 'partner_consent' => true]);
    radarSales($this->measured, 10, 100_000, 'cnpj');
    radarSales($this->measured, 2, 125_000, 'consumidor');

    // Only 9 invoices: under D9's 10, the declared 20 % stands → simples-b2c, "do cadastro".
    $this->few = FiscalRows::client(['name' => 'B Poucas Ltda', 'regime' => 'simples', 'sector' => 'comercio', 'band' => 'b2', 'b2b_share' => 20, 'partner_consent' => false]);
    radarSales($this->few, 9, 100_000, 'cnpj');

    // R$ 300.000 to companies in 90 days (A19) → held R$ 10.600–15.900 a month, das notas. In the
    // last 30 days: one complete invoice and one at risk (no IBSCBS group, R$ 0 to a consumer).
    $this->presumido = FiscalRows::client(['name' => 'C Presumida Ltda', 'regime' => 'presumido', 'sector' => 'industria', 'band' => 'b3', 'b2b_share' => 70]);
    radarSales($this->presumido, 1, 10_000_000, 'cnpj', 5);
    radarSales($this->presumido, 1, 10_000_000, 'cnpj', 40);
    radarSales($this->presumido, 1, 10_000_000, 'cnpj', 80);
    radarSales($this->presumido, 1, 0, 'consumidor', 3, ['items_missing_group' => 1]);
    // Outside the 90 days, and a purchase: never read.
    radarSales($this->presumido, 4, 50_000_000, 'cnpj', 95);
    FiscalRows::purchase($this->presumido, 90_000_000, 3);

    // No XML at all: everything from the cadastro.
    $this->bare = FiscalRows::client(['name' => 'D Sem Notas Ltda', 'regime' => 'real', 'sector' => 'saude', 'band' => 'b4', 'b2b_share' => 30]);
});

describe('the summary (R24, A20)', function () {
    it('analyses every client of the firm and counts those with an opportunity', function () {
        $summary = $this->actingAs($this->ceo)->getJson('/api/radar')->assertOk()->json('data');

        // Every client has `contratos` (an opportunity, R12's table), so all four.
        expect($summary['clients'])->toBe(4)
            ->and($summary['with_opportunity'])->toBe(4);
    });

    it('adds opportunities only to the total — risks never add money', function () {
        $this->actingAs($this->ceo);
        $summary = radarSummary();
        $opportunities = collect($summary['types'])->where('kind', 'oportunidade');
        $risks = collect($summary['types'])->where('kind', 'risco');

        expect($summary['total_cents'])->toBe([
            $opportunities->sum(fn (array $row) => $row['range_cents'][0]),
            $opportunities->sum(fn (array $row) => $row['range_cents'][1]),
        ])
            ->and($risks->count())->toBeGreaterThan(0)
            ->and($summary['risk_types'])->toBe($risks->count())
            ->and($risks->sum(fn (array $row) => $row['range_cents'][1]))->toBeGreaterThan(0);
    });

    it('lists one row per rule type, opportunities first, each biggest range first', function () {
        $this->actingAs($this->ceo);
        $types = radarSummary()['types'];
        $kinds = array_column($types, 'kind');
        $firstRisk = array_search('risco', $kinds, true);
        $opportunityTops = array_map(fn (array $row) => $row['range_cents'][1], array_slice($types, 0, $firstRisk));

        expect(array_slice($kinds, 0, $firstRisk))->each->toBe('oportunidade')
            ->and(array_slice($kinds, $firstRisk))->each->toBe('risco')
            ->and($opportunityTops)->toBe(collect($opportunityTops)->sortDesc()->values()->all())
            ->and(array_column($types, 'rule'))->toHaveCount(count(array_unique(array_column($types, 'rule'))));
    });

    it('reads the measured 80 % B2B share instead of the declared 20 % (D9, A20)', function () {
        $this->actingAs($this->ceo);
        $summary = radarSummary();

        // simples-regular: b2 = R$ 1,8 mi × 1–3 % = R$ 18.000–54.000, one client (the measured one).
        expect(radarRow($summary, 'simples-regular'))->toMatchArray([
            'kind' => 'oportunidade',
            'title' => 'Opção pelo regime regular do IBS/CBS',
            'clients' => 1,
            'range_cents' => [1_800_000, 5_400_000],
            'period' => 'ano',
            'without_consent' => 0,
        ])
            ->and(radarRow($summary, 'simples-b2c')['clients'])->toBe(1)
            ->and(radarRow($summary, 'simples-b2c')['without_consent'])->toBe(1);
    });

    it('names the rule a row rests on, and never lists a rule no client has', function () {
        $this->actingAs($this->ceo);
        $summary = radarSummary();

        expect(radarRow($summary, 'erp-nfe')['citation'])->toStartWith('Presumido ou Real')
            ->and(radarRow($summary, 'agro-60'))->toBeNull()
            ->and(radarRow($summary, 'exportacao'))->toBeNull();
    });
});

describe('the split-payment row (R23, R26, A19)', function () {
    it('is Σ of the held range per month over every client, das notas or from the cadastro', function () {
        $this->actingAs($this->ceo);
        $expected = [
            SplitPaymentCalculator::estimate(1_000_000, 'b2', 20),
            SplitPaymentCalculator::estimate(900_000, 'b2', 20),
            SplitPaymentCalculator::estimate(30_000_000, 'b3', 70),
            SplitPaymentCalculator::estimate(null, 'b4', 30),
        ];

        expect(radarRow(radarSummary(), 'split-payment'))->toMatchArray([
            'kind' => 'risco',
            'clients' => 4,
            'period' => 'mes',
            'range_cents' => [
                array_sum(array_map(fn (array $estimate) => $estimate['held_per_month_cents'][0], $expected)),
                array_sum(array_map(fn (array $estimate) => $estimate['held_per_month_cents'][1], $expected)),
            ],
        ]);
    });

    it('gives R$ 300.000 of B2B sales in 90 days R$ 10.600–15.900 a month das notas, and the bare client the cadastro', function () {
        $this->actingAs($this->ceo);
        $type = radarType('split-payment');

        expect(radarClient($type, $this->presumido->id))->toMatchArray([
            'range_cents' => [1_060_000, 1_590_000],
            'evidence' => ['source' => 'notas', 'window_days' => 90, 'monthly_b2b_cents' => 10_000_000, 'held_per_month_cents' => [1_060_000, 1_590_000]],
        ])
            ->and(radarClient($type, $this->presumido->id)['keys'])->toHaveCount(3)
            ->and(radarClient($type, $this->bare->id))->toMatchArray([
                // b4 = R$ 90 mi × 30 % ÷ 12 = R$ 2.250.000 a month.
                'evidence' => ['source' => 'cadastro', 'monthly_b2b_cents' => 225_000_000, 'held_per_month_cents' => [23_850_000, 35_775_000]],
                'keys' => [],
            ]);
    });
});

describe('a type and its clients (R25, A20)', function () {
    it('shows the measured share, the document count and up to three access keys as the proof', function () {
        $this->actingAs($this->ceo);
        $type = radarType('simples-regular');
        $client = radarClient($type, $this->measured->id);

        expect($type['type'])->toMatchArray(['rule' => 'simples-regular', 'kind' => 'oportunidade', 'clients' => 1])
            ->and($type['type']['detail'])->toBeString()->not->toBeEmpty()
            ->and($client)->toMatchArray([
                'name' => 'A Medida Ltda',
                'cnpj' => $this->measured->cnpj,
                'regime' => 'simples',
                'band' => 'b2',
                'b2b_share' => 20,
                'partner_consent' => true,
                'range_cents' => [1_800_000, 5_400_000],
                'evidence' => ['source' => 'notas', 'window_days' => 90, 'documents' => 12, 'b2b_share' => 80, 'declared_b2b_share' => 20],
            ])
            ->and($client['keys'])->toHaveCount(3)
            ->and($client['keys'])->each->toMatch('/^\d{44}$/');
    });

    it('says "do cadastro" for an item without XML evidence, with no keys', function () {
        $this->actingAs($this->ceo);
        $few = radarClient(radarType('simples-b2c'), $this->few->id);
        $bare = radarClient(radarType('erp-nfe'), $this->bare->id);
        $contracts = radarClient(radarType('contratos'), $this->measured->id);

        expect($few['evidence'])->toBe(['source' => 'cadastro'])
            ->and($few['keys'])->toBe([])
            ->and($few['partner_consent'])->toBeFalse()
            ->and($bare['evidence'])->toBe(['source' => 'cadastro'])
            ->and($contracts['evidence'])->toBe(['source' => 'cadastro'])
            ->and($contracts['keys'])->toBe([]);
    });

    it('proves erp-nfe with the readiness counts of the last 30 days, the invoice at risk first', function () {
        $this->actingAs($this->ceo);
        $client = radarClient(radarType('erp-nfe'), $this->presumido->id);
        $atRiskKey = DB::table('nfe_documents')->where('client_id', $this->presumido->id)->where('items_missing_group', 1)->value('access_key');

        expect($client['evidence'])->toBe(['source' => 'notas', 'window_days' => 30, 'invoices' => 2, 'complete' => 1, 'at_risk' => 1])
            ->and($client['keys'][0])->toBe($atRiskKey)
            ->and($client['keys'])->toHaveCount(2);
    });

    it('sorts the clients biggest range first, ties by id', function () {
        $this->actingAs($this->ceo);
        $tops = array_map(fn (array $client) => $client['range_cents'][1], radarType('contratos')['clients']);

        expect(radarType('contratos')['clients'])->toHaveCount(4)
            ->and($tops)->toBe(collect($tops)->sortDesc()->values()->all())
            ->and(array_column(radarType('contratos')['clients'], 'id'))->toBe([$this->bare->id, $this->presumido->id, $this->measured->id, $this->few->id]);
    });

    it('answers a known rule no client has with no clients, and an unknown one 404', function () {
        $this->actingAs($this->ceo);

        expect(radarType('exportacao')['clients'])->toBe([]);
        $this->getJson('/api/radar/types/perdido')->assertNotFound();
    });
});

describe('who reaches it', function () {
    it('answers a guest 401', function () {
        $this->getJson('/api/radar')->assertUnauthorized();
        $this->getJson('/api/radar/types/contratos')->assertUnauthorized();
    });

    it('refuses the team and a partner with 403, and lets the operator in', function () {
        $this->actingAs(FiscalRows::person('equipe'))->getJson('/api/radar')->assertForbidden();
        $this->actingAs(FiscalRows::person('equipe'))->getJson('/api/radar/types/contratos')->assertForbidden();
        $this->actingAs(FiscalRows::person('parceiro'))->getJson('/api/radar')->assertForbidden();
        $this->actingAs(FiscalRows::person('operador'))->getJson('/api/radar')->assertOk();
    });
});

describe('firm isolation', function () {
    it('never analyses another firm client nor reads its invoices on our client id', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client(['name' => 'Deles Ltda', 'regime' => 'simples', 'b2b_share' => 90], $otherFirm->id);
        radarSales($theirs, 12, 100_000, 'cnpj');
        // Their invoices on OUR bare client's id would turn it "das notas" if the firm scope slipped.
        radarSales($this->bare, 12, 100_000, 'cnpj', attributes: ['firm_id' => $otherFirm->id]);

        $this->actingAs($this->ceo);
        $summary = radarSummary();

        expect($summary['clients'])->toBe(4)
            ->and(radarClient(radarType('split-payment'), $this->bare->id)['evidence']['source'])->toBe('cadastro')
            ->and(radarClient(radarType('contratos'), $theirs->id))->toBeNull();
    });
});

describe('the cost (plan AM6)', function () {
    it('asks the database the same few queries for four clients or twenty-four (no N+1)', function () {
        $this->actingAs($this->ceo);
        $count = function (string $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            test()->getJson($url)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $summary = $count('/api/radar');
        $type = $count('/api/radar/types/split-payment');

        foreach (range(1, 20) as $index) {
            $client = FiscalRows::client(['regime' => $index % 2 ? 'simples' : 'real', 'band' => 'b2']);
            radarSales($client, 11, 100_000, $index % 3 ? 'cnpj' : 'consumidor');
        }

        expect(radarSummary()['clients'])->toBe(24)
            ->and($count('/api/radar'))->toBe($summary)
            ->and($count('/api/radar/types/split-payment'))->toBe($type)
            ->and($summary)->toBeLessThanOrEqual(5)
            ->and($type)->toBeLessThanOrEqual(6);
    });
});
