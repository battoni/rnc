<?php

use App\Modules\Partner\Models\Partner;
use App\Modules\Portfolio\Enums\ContractKind;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use Carbon\CarbonImmutable;
use Database\Seeders\ClientSeeder;
use Database\Seeders\ContractSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FiscalRows;

/*
 * A17 (rnc-3 R19, AM7, AM17): the contract alert.
 *
 *   GET /api/contracts   {summary: {at_risk, total, annual_value_cents, exposure_cents},
 *                         contracts: [{id, client: {id, name}, kind, annual_value_cents, start_year,
 *                                      end_year, years_exposed, exposure_cents}]}
 *
 * The contracts ending in 2027 or later without the Reforma clause, by exposure (annual value ×
 * years in 2027–2033) highest first, ties by id; the KPIs are "N de M" and the at-risk ones' annual
 * value and exposure. A fixed number of queries whatever the size (P13). Behind `contracts.manage`.
 * The seed (R29, AM10): the demo's 40 contracts, on seeded clients only.
 */
function alertContract(Client $client, array $attributes = []): ClientContract
{
    return ClientContract::withoutGlobalScopes()->create([
        'firm_id' => $client->firm_id,
        'client_id' => $client->id,
        'kind' => 'fornecimento',
        'annual_value_cents' => 1_000_000,
        'start_year' => 2025,
        'end_year' => 2030,
        'has_reforma_clause' => false,
        ...$attributes,
    ]);
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->reinaldo = FiscalRows::person('ceo');
});

describe('the list (A17)', function () {
    beforeEach(function () {
        $this->acme = FiscalRows::client(['name' => 'Acme Indústria Ltda']);
        $this->beta = FiscalRows::client(['name' => 'Beta Serviços Ltda']);
        alertContract($this->acme, ['end_year' => 2026, 'start_year' => 2022, 'annual_value_cents' => 90_000_000]);
        alertContract($this->acme, ['has_reforma_clause' => true, 'annual_value_cents' => 90_000_000]);
        $this->long = alertContract($this->acme, ['kind' => 'locacao', 'start_year' => 2025, 'end_year' => 2035, 'annual_value_cents' => 1_000_000]);
        $this->tieFirst = alertContract($this->beta, ['kind' => 'manutencao', 'start_year' => 2030, 'end_year' => 2033, 'annual_value_cents' => 2_000_000]);
        $this->short = alertContract($this->beta, ['kind' => 'distribuicao', 'start_year' => 2028, 'end_year' => 2030, 'annual_value_cents' => 3_000_000]);
        $this->tieSecond = alertContract($this->acme, ['kind' => 'prestacao_servicos', 'start_year' => 2030, 'end_year' => 2033, 'annual_value_cents' => 2_000_000]);
    });

    it('leaves out a contract ending 2026 and one with the clause, and ranks by exposure then id', function () {
        $rows = $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk()->json('data.contracts');

        expect(array_column($rows, 'id'))->toBe([$this->short->id, $this->tieFirst->id, $this->tieSecond->id, $this->long->id]);
    });

    it('counts 7 years to a contract ending 2035, 3 to 2028–2030 and 4 to 2030–2033', function () {
        $rows = collect($this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data.contracts'))->keyBy('id');

        expect($rows[$this->long->id])->toMatchArray(['years_exposed' => 7, 'exposure_cents' => 7_000_000])
            ->and($rows[$this->short->id])->toMatchArray(['years_exposed' => 3, 'exposure_cents' => 9_000_000])
            ->and($rows[$this->tieFirst->id])->toMatchArray(['years_exposed' => 4, 'exposure_cents' => 8_000_000]);
    });

    it('answers each row with its client, kind, value and years', function () {
        $row = collect($this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data.contracts'))->firstWhere('id', $this->short->id);

        expect($row)->toBe([
            'id' => $this->short->id,
            'client' => ['id' => $this->beta->id, 'name' => 'Beta Serviços Ltda', 'partner_consent' => null, 'partner_consent_via' => null],
            'kind' => 'distribuicao',
            'annual_value_cents' => 3_000_000,
            'start_year' => 2028,
            'end_year' => 2030,
            'years_exposed' => 3,
            'exposure_cents' => 9_000_000,
            'referred_to' => [],
        ]);
    });

    it('sums the KPIs as by hand: 4 of 6, the at-risk annual value and their exposure', function () {
        $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk()->assertJsonPath('data.summary', [
            'at_risk' => 4,
            'total' => 6,
            'annual_value_cents' => 1_000_000 + 2_000_000 + 3_000_000 + 2_000_000,
            'exposure_cents' => 7_000_000 + 8_000_000 + 9_000_000 + 8_000_000,
            'referred' => 0,
        ]);
    });

    it('answers in the same number of queries for 4 rows and for 40 (P13)', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk();

            return count(DB::getQueryLog());
        };

        $few = $count();
        foreach (range(1, 36) as $index) {
            alertContract(FiscalRows::client(), ['annual_value_cents' => 100_000 + $index]);
        }

        expect($count())->toBe($few);
    });
});

describe('an empty alert', function () {
    it('answers no rows and zero KPIs', function () {
        $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk()->assertJsonPath('data', [
            'summary' => ['at_risk' => 0, 'total' => 0, 'annual_value_cents' => 0, 'exposure_cents' => 0, 'referred' => 0],
            'contracts' => [],
        ]);
    });
});

describe('the seed (R29, AM10)', function () {
    beforeEach(function () {
        $this->seed([ClientSeeder::class, ContractSeeder::class]);
    });

    it("seeds the demo's 40 contracts: its kinds, R\$ 40.000–1.200.000 a year, start 2022–2026, end 2026–2033", function () {
        $rows = ClientContract::withoutGlobalScopes()->get();

        expect($rows)->toHaveCount(40)
            ->and($rows->pluck('kind')->unique()->diff(array_column(ContractKind::cases(), 'value'))->all())->toBe([])
            ->and($rows->min('annual_value_cents'))->toBeGreaterThanOrEqual(4_000_000)
            ->and($rows->max('annual_value_cents'))->toBeLessThanOrEqual(120_000_000)
            ->and($rows->every(fn ($row) => $row->annual_value_cents % 1_000_000 === 0))->toBeTrue()
            ->and($rows->min('start_year'))->toBeGreaterThanOrEqual(2022)
            ->and($rows->max('start_year'))->toBeLessThanOrEqual(2026)
            ->and($rows->min('end_year'))->toBeGreaterThanOrEqual(2026)
            ->and($rows->max('end_year'))->toBeLessThanOrEqual(2033)
            ->and($rows->every(fn ($row) => $row->end_year >= $row->start_year))->toBeTrue()
            ->and($rows->where('has_reforma_clause', true)->count())->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(16)
            ->and($rows->whereNotNull('clause_marked_at')->count())->toBe(0);
    });

    it('lists 20 to 30 at-risk contracts, the same on every run', function () {
        $first = $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk()->json('data');
        $this->seed(ContractSeeder::class);
        $second = $this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data');

        expect($first['summary']['at_risk'])->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(30)
            ->and($first['summary']['total'])->toBe(40)
            ->and($second['summary'])->toBe($first['summary']);
    });

    it('puts every seeded contract on a seeded client, never on another (AM10)', function () {
        $cnpjs = Client::withoutGlobalScopes()->whereIn('id', ClientContract::withoutGlobalScopes()->pluck('client_id'))->pluck('cnpj');

        expect($cnpjs)->not->toBeEmpty()
            ->and($cnpjs->every(fn (string $cnpj) => preg_match('/^999\d{5}9999\d{2}$/', $cnpj) === 1))->toBeTrue();
    });

    it('puts at least 5 at-risk contracts on clients without consent, so the blocked button shows (F6b, R20)', function () {
        $rows = $this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data.contracts');

        expect(collect($rows)->filter(fn (array $row) => $row['client']['partner_consent'] !== true)->count())->toBeGreaterThanOrEqual(5)
            ->and(collect($rows)->filter(fn (array $row) => $row['client']['partner_consent'] === true)->count())->toBeGreaterThanOrEqual(10);
    });

    it('seeds nothing in production', function () {
        ClientContract::withoutGlobalScopes()->delete();
        app()->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` would stop to ask "really, in production?".
        app(ContractSeeder::class)->setContainer(app())->__invoke();

        expect(ClientContract::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('consent and the legal referral on the row (F6b, R19, R20)', function () {
    beforeEach(function () {
        Mail::fake();
        $this->lawyer = Partner::withoutGlobalScopes()->create([
            'firm_id' => FiscalRows::francoId(), 'name' => 'Andrade Tributário (exemplo)', 'specialty' => 'advocacia_tributaria',
            'email' => 'andrade@parceiro.exemplo', 'active' => true,
        ]);
        $this->consented = FiscalRows::client(['name' => 'Gama Consentida Ltda', 'partner_consent' => true, 'partner_consent_at' => CarbonImmutable::parse('2020-03-01T12:00:00Z'), 'partner_consent_via' => 'contrato']);
        $this->refused = FiscalRows::client(['name' => 'Delta Recusou Ltda', 'partner_consent' => false, 'partner_consent_at' => CarbonImmutable::parse('2020-03-01T12:00:00Z'), 'partner_consent_via' => 'contrato']);
        $this->sent = alertContract($this->consented, ['annual_value_cents' => 50_000_000]);
        $this->waiting = alertContract($this->consented, ['kind' => 'locacao', 'annual_value_cents' => 40_000_000]);
        $this->blocked = alertContract($this->refused, ['annual_value_cents' => 30_000_000]);
        $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->sent->id}/referrals", ['partner_id' => $this->lawyer->id])->assertCreated();
    });

    it("answers each row's client consent and the partners it was sent to", function () {
        $rows = collect($this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data.contracts'))->keyBy('id');

        expect($rows[$this->sent->id]['client'])->toMatchArray(['partner_consent' => true, 'partner_consent_via' => 'contrato'])
            ->and($rows[$this->sent->id]['referred_to'])->toBe([['id' => $this->lawyer->id, 'name' => 'Andrade Tributário (exemplo)']])
            ->and($rows[$this->waiting->id]['referred_to'])->toBe([])
            ->and($rows[$this->blocked->id]['client'])->toMatchArray(['partner_consent' => false]);
    });

    it('counts "Encaminhados ao jurídico": at-risk contracts with a referral', function () {
        $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertJsonPath('data.summary.referred', 1);

        $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->sent->id}/clause")->assertOk();

        $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertJsonPath('data.summary.referred', 0);
    });

    it('answers in the same number of queries with referrals as without (P13)', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->reinaldo)->getJson('/api/contracts')->assertOk();

            return count(DB::getQueryLog());
        };

        $few = $count();
        $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->waiting->id}/referrals", ['partner_id' => $this->lawyer->id])->assertCreated();

        expect($count())->toBe($few);
    });
});
