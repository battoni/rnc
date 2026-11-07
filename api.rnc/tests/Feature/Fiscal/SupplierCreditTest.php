<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\ClientEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\FiscalRows;

/*
 * "Crédito de fornecedores" (rnc-2 F7, R21–R22, A18, A17's Pest half; plan AM6, AM13):
 *
 *   GET  /api/fiscal/supplier-credit?sector=        {summary, clients: [row]}
 *   POST /api/fiscal/supplier-credit/{id}/review    201 the `fornecedores.revisao` timeline line;
 *                                                   422 `no_simples_purchases` at 0 %
 *
 * Presumido and Real clients with purchase invoices (entrada) in the last 90 days: purchases a month,
 * the share bought from Simples/MEI suppliers (CRT 1, 2, 4) by value against regime normal (CRT 3),
 * and the credit lost per year from 2027 as a range (`SupplierCreditCalculator`). Biggest range
 * first. Behind `fiscal.read` (equipe, ceo) — the review too (AM13).
 */
function supplierCreditList(string $query = ''): array
{
    return test()->getJson('/api/fiscal/supplier-credit'.$query)->assertOk()->json('data');
}

function supplierCreditRow(array $data, int $id): ?array
{
    return collect($data['clients'])->firstWhere('id', $id);
}

beforeEach(function () {
    Http::preventStrayRequests();
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->equipe = FiscalRows::person('equipe');

    // A18 end to end: R$ 300.000 bought in 90 days (R$ 100.000 a month), R$ 90.000 from Simples.
    $this->buyer = FiscalRows::client(['name' => 'Compradora Ltda', 'sector' => 'industria']);
    FiscalRows::purchase($this->buyer, 5_000_000, 1);
    FiscalRows::purchase($this->buyer, 4_000_000, 4, daysAgo: 80);
    FiscalRows::purchase($this->buyer, 21_000_000, 3, daysAgo: 45);
    // Outside the window, and the client's own sales: never counted.
    FiscalRows::purchase($this->buyer, 99_000_000, 1, daysAgo: 91);
    FiscalRows::sales($this->buyer, 2, 'complete', attributes: ['total_cents' => 50_000_000, 'emitter_crt' => 1]);

    // Buys only from regime normal, and once from a supplier whose CRT could not be read.
    $this->normal = FiscalRows::client(['name' => 'Normal Ltda', 'sector' => 'servicos', 'regime' => 'real']);
    FiscalRows::purchase($this->normal, 3_000_000, 3);
    FiscalRows::purchase($this->normal, 1_500_000, null);

    // A little from a Simples supplier with excess sub-limit (CRT 2).
    $this->small = FiscalRows::client(['name' => 'Pequena Ltda']);
    FiscalRows::purchase($this->small, 600_000, 2);
    FiscalRows::purchase($this->small, 2_400_000, 3);

    $this->simples = FiscalRows::client(['name' => 'Simples Ltda', 'regime' => 'simples']);
    FiscalRows::purchase($this->simples, 9_000_000, 1);

    $this->none = FiscalRows::client(['name' => 'Sem Compras Ltda']);
});

describe('the supplier-credit list (R21, A18)', function () {
    it('gives the A18 client R$ 100.000 a month, 30 % Simples and R$ 77.400–84.600 a year', function () {
        $this->actingAs($this->equipe);

        expect(supplierCreditRow(supplierCreditList(), $this->buyer->id))->toMatchArray([
            'id' => $this->buyer->id,
            'name' => 'Compradora Ltda',
            'sector' => 'industria',
            'regime' => 'presumido',
            'documents' => 3,
            'monthly_purchases_cents' => 10_000_000,
            'simples_share' => 30.0,
            'normal_share' => 70.0,
            'lost_per_year_cents' => [7_740_000, 8_460_000],
            'proposed' => false,
        ]);
    });

    it('gives 0–0 to a client buying only from regime normal, an unread CRT counting as not Simples', function () {
        $this->actingAs($this->equipe);

        expect(supplierCreditRow(supplierCreditList(), $this->normal->id))->toMatchArray([
            'monthly_purchases_cents' => 1_500_000,
            'simples_share' => 0.0,
            'normal_share' => 100.0,
            'lost_per_year_cents' => [0, 0],
        ]);
    });

    it('sorts by the top of the range, leaves Simples clients and clients without purchases out', function () {
        $this->actingAs($this->equipe);

        expect(array_column(supplierCreditList()['clients'], 'id'))->toBe([$this->buyer->id, $this->small->id, $this->normal->id]);
    });

    it('sums the KPIs: lost per year, N of M buying from Simples, mean Simples share, revisions proposed', function () {
        $this->actingAs($this->equipe);
        // Pequena: R$ 30.000 in 90 days, 20 % Simples → 6.000 × 4 × 0,215 = 5.160 and × 0,235 = 5.640.
        // JSON keeps no zero fraction: 25.0 is answered as 25 (`toEqual`).
        expect(supplierCreditList()['summary'])->toEqual([
            'clients' => 3,
            'buying_from_simples' => 2,
            'mean_simples_share' => 25.0,
            'lost_per_year_cents' => [7_740_000 + 516_000, 8_460_000 + 564_000],
            'proposed' => 0,
        ]);
    });

    it('filters by sector, the KPIs following the filter', function () {
        $this->actingAs($this->equipe);
        $services = supplierCreditList('?sector=servicos');

        expect(array_column($services['clients'], 'id'))->toBe([$this->normal->id])
            ->and($services['summary'])->toMatchArray(['clients' => 1, 'buying_from_simples' => 0, 'mean_simples_share' => 0.0, 'lost_per_year_cents' => [0, 0]]);
        $this->getJson('/api/fiscal/supplier-credit?sector=espacial')->assertUnprocessable()->assertJsonValidationErrors('sector');
    });
});

describe('"Propor revisão" (R22, A17)', function () {
    it('writes one fornecedores.revisao line with who and the figures, and the row shows it proposed', function () {
        $response = $this->actingAs($this->equipe)->postJson("/api/fiscal/supplier-credit/{$this->buyer->id}/review")->assertCreated();

        $event = ClientEvent::withoutGlobalScopes()->sole();
        $data = supplierCreditList();

        expect($event->type)->toBe('fornecedores.revisao')
            ->and($event->client_id)->toBe($this->buyer->id)
            ->and($event->user_id)->toBe($this->equipe->id)
            ->and($event->data)->toEqual(['simples_share' => 30, 'lost_per_year_cents' => [7_740_000, 8_460_000]])
            ->and($response->json('data.type'))->toBe('fornecedores.revisao')
            ->and($response->json('data.user.id'))->toBe($this->equipe->id)
            ->and(supplierCreditRow($data, $this->buyer->id)['proposed'])->toBeTrue()
            ->and(supplierCreditRow($data, $this->small->id)['proposed'])->toBeFalse()
            ->and($data['summary']['proposed'])->toBe(1);
    });

    it('refuses a client with 0 % from Simples 422 no_simples_purchases, writing nothing', function () {
        $this->actingAs($this->equipe);

        $this->postJson("/api/fiscal/supplier-credit/{$this->normal->id}/review")->assertUnprocessable()->assertJsonPath('code', 'no_simples_purchases');
        $this->postJson("/api/fiscal/supplier-credit/{$this->none->id}/review")->assertUnprocessable()->assertJsonPath('code', 'no_simples_purchases');

        expect(ClientEvent::withoutGlobalScopes()->count())->toBe(0);
    });

    it('answers a Simples client, an unknown id and another firm client 404', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client([], $otherFirm->id);
        FiscalRows::purchase($theirs, 9_000_000, 1);
        $this->actingAs($this->equipe);

        $this->postJson("/api/fiscal/supplier-credit/{$this->simples->id}/review")->assertNotFound();
        $this->postJson('/api/fiscal/supplier-credit/999999/review')->assertNotFound();
        $this->postJson("/api/fiscal/supplier-credit/{$theirs->id}/review")->assertNotFound();

        expect(ClientEvent::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('who reads it (D14, AM13)', function () {
    it('answers a guest 401 and a partner login 403, and lets the ceo in', function () {
        $this->getJson('/api/fiscal/supplier-credit')->assertUnauthorized();
        $this->actingAs(FiscalRows::person('parceiro'))->getJson('/api/fiscal/supplier-credit')->assertForbidden();
        $this->actingAs(FiscalRows::person('parceiro'))->postJson("/api/fiscal/supplier-credit/{$this->buyer->id}/review")->assertForbidden();
        $this->actingAs(FiscalRows::person('ceo'))->getJson('/api/fiscal/supplier-credit')->assertOk();
    });
});

describe('firm isolation', function () {
    it('never lists another firm client nor counts its purchases', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client([], $otherFirm->id);
        FiscalRows::purchase($theirs, 9_000_000, 1);
        FiscalRows::document($this->normal, 'entrada', ['firm_id' => $otherFirm->id, 'total_cents' => 9_000_000, 'emitter_crt' => 1]);

        $this->actingAs($this->equipe);
        $data = supplierCreditList();

        expect(array_column($data['clients'], 'id'))->not->toContain($theirs->id)
            ->and(supplierCreditRow($data, $this->normal->id)['simples_share'])->toEqual(0);
    });
});

describe('the cost (plan AM6)', function () {
    it('asks the database the same few queries for three clients or twenty-three (no N+1)', function () {
        $this->actingAs($this->equipe);
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            supplierCreditList();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $few = $count();

        foreach (range(1, 20) as $index) {
            $client = FiscalRows::client();
            FiscalRows::purchase($client, 100_000 * $index, $index % 2 ? 1 : 3);
        }
        $this->postJson("/api/fiscal/supplier-credit/{$this->buyer->id}/review")->assertCreated();

        expect(count(supplierCreditList()['clients']))->toBe(23)
            ->and($count())->toBe($few)
            ->and($few)->toBeLessThanOrEqual(6);
    });
});
