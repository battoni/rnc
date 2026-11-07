<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Fiscal\Enums\ReadinessStatus;
use App\Modules\Portfolio\Models\ClientEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\FiscalRows;

/*
 * "Prontidão da NF-e" (rnc-2 F7, R18–R20, A16, A17's Pest half; plan AM6, AM13):
 *
 *   GET  /api/fiscal/readiness?status=&system=   {summary, systems, clients: [row]}
 *   GET  /api/fiscal/readiness/{id}              row + issues with up to five sample invoices each
 *   POST /api/fiscal/readiness/{id}/notice       201 the `nfe.aviso` line on the client's timeline
 *
 * Presumido and Real clients only (Simples out until 2027), their issued invoices (saída) of the
 * last 30 days. An invoice is complete when every item passes the three checks; it is at risk of
 * rejection 1115 when an item has no IBSCBS group. Status: crítico under 80 % or with any at risk,
 * ok at 95 % or more, else atenção; no sales → `sem_notas`, listed last. All behind `fiscal.read`
 * (equipe, ceo) — the notice too (AM13).
 */
function readinessList(string $query = ''): array
{
    return test()->getJson('/api/fiscal/readiness'.$query)->assertOk()->json('data');
}

/** @return list<int> the listed client ids, in order */
function readinessIds(array $data): array
{
    return array_column($data['clients'], 'id');
}

function readinessRow(array $data, int $id): array
{
    return collect($data['clients'])->firstWhere('id', $id);
}

beforeEach(function () {
    Http::preventStrayRequests();
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->equipe = FiscalRows::person('equipe');

    // A16's fixtures: each client's last-30-days sales.
    $this->ok = FiscalRows::client(['name' => 'A Pronta Ltda']);
    FiscalRows::sales($this->ok, 20, 'complete', attributes: ['system' => 'TOTVS Protheus 12.1']);
    FiscalRows::sales($this->ok, 4, 'complete', attributes: ['system' => 'Omie 2.4']);
    FiscalRows::sales($this->ok, 1, 'without_test_values', attributes: ['system' => 'Omie 2.4']);

    $this->attention = FiscalRows::client(['name' => 'B Atenção Ltda', 'regime' => 'real']);
    FiscalRows::sales($this->attention, 9, 'complete', attributes: ['system' => 'SAP Business One 10.0']);
    FiscalRows::sales($this->attention, 1, 'missing_codes', attributes: ['system' => 'SAP Business One 10.0']);

    $this->atRisk = FiscalRows::client(['name' => 'C Em Risco Ltda']);
    FiscalRows::sales($this->atRisk, 24, 'complete');
    FiscalRows::sales($this->atRisk, 1, 'missing_group');

    $this->low = FiscalRows::client(['name' => 'D Baixa Ltda']);
    FiscalRows::sales($this->low, 7, 'complete');
    FiscalRows::sales($this->low, 3, 'missing_codes');

    $this->simples = FiscalRows::client(['name' => 'E Simples Ltda', 'regime' => 'simples']);
    FiscalRows::sales($this->simples, 5, 'missing_group');

    $this->silent = FiscalRows::client(['name' => 'F Sem Notas Ltda']);
    // Outside the window or not a sale: never counted.
    FiscalRows::sales($this->silent, 3, 'missing_group', daysAgo: 31);
    FiscalRows::purchase($this->silent, 100_000, 1);
});

describe('the readiness list (A16)', function () {
    it('gives each fixture its status: 96 % 0 risk ok, 90 % atenção, 96 % 1 risk crítico, 70 % crítico', function () {
        $data = $this->actingAs($this->equipe)->getJson('/api/fiscal/readiness')->assertOk()->json('data');

        expect(readinessRow($data, $this->ok->id))->toMatchArray(['invoices' => 25, 'complete' => 24, 'complete_pct' => 96.0, 'at_risk' => 0, 'status' => 'ok'])
            ->and(readinessRow($data, $this->attention->id))->toMatchArray(['invoices' => 10, 'complete' => 9, 'complete_pct' => 90.0, 'at_risk' => 0, 'status' => 'atencao'])
            ->and(readinessRow($data, $this->atRisk->id))->toMatchArray(['invoices' => 25, 'complete' => 24, 'complete_pct' => 96.0, 'at_risk' => 1, 'status' => 'critico'])
            ->and(readinessRow($data, $this->low->id))->toMatchArray(['invoices' => 10, 'complete' => 7, 'complete_pct' => 70.0, 'at_risk' => 0, 'status' => 'critico']);
    });

    it('leaves the Simples client out and lists a client without sales as sem_notas, last', function () {
        $this->actingAs($this->equipe);
        $data = readinessList();

        expect(readinessIds($data))->not->toContain($this->simples->id)
            ->and(readinessIds($data))->toBe([$this->atRisk->id, $this->low->id, $this->attention->id, $this->ok->id, $this->silent->id])
            ->and(readinessRow($data, $this->silent->id))->toMatchArray(['invoices' => 0, 'complete' => 0, 'complete_pct' => null, 'at_risk' => 0, 'status' => 'sem_notas', 'system' => null]);
    });

    it('names the most frequent emitting system and carries the client profile', function () {
        $this->actingAs($this->equipe);
        $row = readinessRow(readinessList(), $this->ok->id);

        expect($row)->toMatchArray([
            'id' => $this->ok->id,
            'name' => 'A Pronta Ltda',
            'cnpj' => $this->ok->cnpj,
            'sector' => 'comercio',
            'regime' => 'presumido',
            'system' => 'TOTVS Protheus 12.1',
        ]);
    });

    it('sums the hero and KPIs over every listed client (received-weighted %)', function () {
        $this->actingAs($this->equipe);

        expect(readinessList()['summary'])->toBe([
            'clients' => 5,
            'invoices' => 70,
            'complete' => 64,
            // 64 of 70 invoices, weighted by what was received: 91,4 %.
            'complete_pct' => 91.4,
            'at_risk' => 1,
            'by_status' => ['critico' => 2, 'atencao' => 1, 'ok' => 1, 'sem_notas' => 1],
        ]);
    });

    it('filters by status and by system, the summary and the system list unfiltered', function () {
        $this->actingAs($this->equipe);
        $critical = readinessList('?status=critico');
        $sap = readinessList('?system='.urlencode('SAP Business One 10.0'));
        $silent = readinessList('?status=sem_notas');

        expect(readinessIds($critical))->toBe([$this->atRisk->id, $this->low->id])
            ->and($critical['summary']['clients'])->toBe(5)
            ->and(readinessIds($sap))->toBe([$this->attention->id])
            ->and($sap['systems'])->toBe(['SAP Business One 10.0', 'TOTVS Protheus 12.1'])
            ->and(readinessIds($silent))->toBe([$this->silent->id]);
    });

    it('refuses an unknown status', function () {
        $this->actingAs($this->equipe)->getJson('/api/fiscal/readiness?status=perdido')->assertUnprocessable()->assertJsonValidationErrors('status');
    });

    it('puts the thresholds at 95 and 80, a single invoice at risk making it crítico', function (int $invoices, int $complete, int $atRisk, string $expected) {
        expect(ReadinessStatus::fromCounts($invoices, $complete, $atRisk)->value)->toBe($expected);
    })->with([
        '95 % is ok' => [20, 19, 0, 'ok'],
        '94,9 % is atenção' => [1000, 949, 0, 'atencao'],
        '80 % is atenção' => [5, 4, 0, 'atencao'],
        '79,9 % is crítico' => [1000, 799, 0, 'critico'],
        '100 % with one at risk is crítico' => [10, 10, 1, 'critico'],
        'no invoice is sem_notas' => [0, 0, 0, 'sem_notas'],
    ]);
});

describe('the client detail (A16, R19)', function () {
    it('counts the issue lines from the fixture items and names up to five sample invoices with the item', function () {
        $this->actingAs($this->equipe);
        $risk = $this->getJson("/api/fiscal/readiness/{$this->atRisk->id}")->assertOk()->json('data');
        $low = $this->getJson("/api/fiscal/readiness/{$this->low->id}")->assertOk()->json('data');
        $ok = $this->getJson("/api/fiscal/readiness/{$this->ok->id}")->assertOk()->json('data');

        expect($risk['status'])->toBe('critico')
            ->and($risk['issues'])->toHaveCount(1)
            ->and($risk['issues'][0])->toMatchArray(['code' => 'missing_group', 'invoices' => 1, 'risk' => true])
            ->and($risk['issues'][0]['samples'][0])->toMatchArray(['series' => 1, 'item' => 2])
            ->and($low['issues'])->toHaveCount(1)
            ->and($low['issues'][0])->toMatchArray(['code' => 'missing_codes', 'invoices' => 3, 'risk' => false])
            ->and(array_column($low['issues'][0]['samples'], 'item'))->toBe([1, 1, 1])
            ->and($ok['issues'])->toHaveCount(1)
            ->and($ok['issues'][0])->toMatchArray(['code' => 'without_test_values', 'invoices' => 1, 'risk' => false])
            ->and($ok['issues'][0]['samples'][0]['item'])->toBe(3);
    });

    it('classifies an invoice once, under its worst issue, so the lines add up to the incomplete ones', function () {
        $mixed = FiscalRows::client();
        FiscalRows::sales($mixed, 1, 'complete', attributes: [
            'items_missing_group' => 1, 'items_missing_codes' => 1, 'items_without_test_values' => 1,
            'sample' => ['missing_group' => [4], 'missing_codes' => [2], 'without_test_values' => [1]],
        ]);
        FiscalRows::sales($mixed, 7, 'missing_codes');
        FiscalRows::sales($mixed, 2, 'complete');

        $data = $this->actingAs($this->equipe)->getJson("/api/fiscal/readiness/{$mixed->id}")->assertOk()->json('data');

        expect(array_column($data['issues'], 'invoices', 'code'))->toBe(['missing_group' => 1, 'missing_codes' => 7])
            ->and($data['issues'][0]['samples'][0]['item'])->toBe(4)
            ->and($data['issues'][1]['samples'])->toHaveCount(5)
            ->and($data['invoices'] - $data['complete'])->toBe(8);
    });

    it('lists the samples newest first', function () {
        $client = FiscalRows::client();
        FiscalRows::sales($client, 1, 'missing_codes', daysAgo: 20, attributes: ['number' => 111]);
        FiscalRows::sales($client, 1, 'missing_codes', daysAgo: 2, attributes: ['number' => 222]);

        $samples = $this->actingAs($this->equipe)->getJson("/api/fiscal/readiness/{$client->id}")->json('data.issues.0.samples');

        expect(array_column($samples, 'number'))->toBe([222, 111]);
    });

    it('answers a client without sales with no issues, and a Simples client 404', function () {
        $this->actingAs($this->equipe);

        $this->getJson("/api/fiscal/readiness/{$this->silent->id}")->assertOk()
            ->assertJsonPath('data.status', 'sem_notas')
            ->assertJsonPath('data.issues', []);
        $this->getJson("/api/fiscal/readiness/{$this->simples->id}")->assertNotFound();
        $this->getJson('/api/fiscal/readiness/999999')->assertNotFound();
    });
});

describe('"Registrar aviso" (R20, A17)', function () {
    it('writes one nfe.aviso line on the client timeline with who and the counts, under fiscal.read', function () {
        $response = $this->actingAs($this->equipe)->postJson("/api/fiscal/readiness/{$this->atRisk->id}/notice")->assertCreated();

        $event = ClientEvent::withoutGlobalScopes()->sole();
        expect($event->client_id)->toBe($this->atRisk->id)
            ->and($event->type)->toBe('nfe.aviso')
            ->and($event->user_id)->toBe($this->equipe->id)
            ->and($event->firm_id)->toBe(FiscalRows::francoId())
            // JSON keeps no zero fraction: 96.0 is stored and answered as 96.
            ->and($event->data)->toEqual(['invoices' => 25, 'complete_pct' => 96, 'at_risk' => 1, 'status' => 'critico'])
            ->and($response->json('data'))->toMatchArray(['id' => $event->id, 'type' => 'nfe.aviso'])
            ->and($response->json('data.user.id'))->toBe($this->equipe->id)
            ->and($response->json('data.at'))->toBe('2026-11-09T16:00:00+00:00');
    });

    it('refuses a Simples client and another firm client with 404, writing nothing', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client([], $otherFirm->id);
        FiscalRows::sales($theirs, 3, 'missing_group');
        $this->actingAs($this->equipe);

        $this->postJson("/api/fiscal/readiness/{$this->simples->id}/notice")->assertNotFound();
        $this->postJson("/api/fiscal/readiness/{$theirs->id}/notice")->assertNotFound();

        expect(ClientEvent::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('who reads it (D14, AM13)', function () {
    it('answers a guest 401', function () {
        $this->getJson('/api/fiscal/readiness')->assertUnauthorized();
        $this->postJson("/api/fiscal/readiness/{$this->ok->id}/notice")->assertUnauthorized();
    });

    it('lets equipe and ceo read and register, and refuses a partner login 403', function () {
        $this->actingAs(FiscalRows::person('ceo'))->getJson('/api/fiscal/readiness')->assertOk();
        $this->actingAs(FiscalRows::person('ceo'))->postJson("/api/fiscal/readiness/{$this->ok->id}/notice")->assertCreated();
        $this->actingAs(FiscalRows::person('parceiro'))->getJson('/api/fiscal/readiness')->assertForbidden();
        $this->actingAs(FiscalRows::person('parceiro'))->getJson("/api/fiscal/readiness/{$this->ok->id}")->assertForbidden();
        $this->actingAs(FiscalRows::person('parceiro'))->postJson("/api/fiscal/readiness/{$this->ok->id}/notice")->assertForbidden();
    });
});

describe('firm isolation', function () {
    it('never lists another firm client or counts its invoices, and answers its detail 404', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client(['name' => 'Deles Ltda'], $otherFirm->id);
        FiscalRows::sales($theirs, 9, 'missing_group');
        // Their invoice on OUR client's id would only be read if the firm scope slipped.
        FiscalRows::document($this->ok, 'saida', ['firm_id' => $otherFirm->id, 'items_missing_group' => 1]);

        $this->actingAs($this->equipe);
        $data = readinessList();

        expect(readinessIds($data))->not->toContain($theirs->id)
            ->and(readinessRow($data, $this->ok->id)['at_risk'])->toBe(0)
            ->and($data['summary']['invoices'])->toBe(70);
        $this->getJson("/api/fiscal/readiness/{$theirs->id}")->assertNotFound();
    });
});

describe('the cost (plan AM6)', function () {
    it('asks the database the same few queries for five clients or twenty-five (no N+1)', function () {
        $this->actingAs($this->equipe);
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            readinessList();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $few = $count();

        foreach (range(1, 20) as $index) {
            $client = FiscalRows::client(['regime' => $index % 2 ? 'presumido' : 'real']);
            FiscalRows::sales($client, 2, 'missing_codes', attributes: ['system' => "ERP {$index}"]);
        }

        expect(count(readinessList()['clients']))->toBe(25)
            ->and($count())->toBe($few)
            ->and($few)->toBeLessThanOrEqual(6);
    });
});
