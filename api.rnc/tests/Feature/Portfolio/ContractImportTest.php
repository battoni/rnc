<?php

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\ClientContract;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Support\FiscalRows;

/*
 * A16 (rnc-3 R17, R18, AM16): the contracts CSV.
 *
 *   POST /api/contracts/import     multipart `file` → {created, updated, total, errors: [{line, field, code}], total_errors}
 *   GET  /api/contracts/template   `modelo-contratos.csv`: BOM and the header row
 *
 * `cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma`, read as the clients CSV is. Lines are
 * independent: good lines are stored and bad ones named (a bad line never stops the others). The
 * same client + kind + start + end updates its row. An empty clause is "não" on a new row and keeps
 * what is stored on an update; a contract marked through "Cláusula incluída" is never downgraded by
 * a re-import (AM16). Behind `contracts.manage` (gestao, operador), 10 imports a minute.
 */
const CONTRACTS_CSV_HEADER = 'cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma';

function contractsCsv(array $lines, string $header = CONTRACTS_CSV_HEADER, string $prefix = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent('contratos.csv', $prefix.implode("\n", [$header, ...$lines])."\n");
}

function importContracts(UploadedFile $file, string|User $who = 'gestao'): TestResponse
{
    return test()->actingAs(is_string($who) ? FiscalRows::person($who) : $who)->post('/api/contracts/import', ['file' => $file], ['Accept' => 'application/json']);
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->acme = FiscalRows::client(['name' => 'Acme Indústria Ltda', 'cnpj' => '11222333000181']);
    $this->beta = FiscalRows::client(['name' => 'Beta Serviços Ltda', 'cnpj' => '33444555000181']);
});

describe('a good file (A16)', function () {
    it('imports labels and slugs, "1.200.000,00" and sim/não in any case and accent', function () {
        $response = importContracts(contractsCsv([
            '11.222.333/0001-81;Fornecimento;1.200.000,00;2024;2030;SIM',
            '11222333000181;prestacao_servicos;450000;2025;2033;nao',
            '33.444.555/0001-81;Prestação de serviços;R$ 80.000,50;2026;2028;Não',
            '33444555000181;LOCAÇÃO;95000.00;2022;2031;',
            '33444555000181;distribuicao;120000;2023;2027;sim',
            '33444555000181;Manutenção;60000;2026;2029;NÃO',
        ]))->assertOk();

        $response->assertJsonPath('data', ['created' => 6, 'updated' => 0, 'total' => 6, 'errors' => [], 'total_errors' => 0]);

        $rows = ClientContract::withoutGlobalScopes()->orderBy('id')->get();
        expect($rows->map(fn ($row) => [$row->client_id, $row->kind, $row->annual_value_cents, $row->start_year, $row->end_year, $row->has_reforma_clause])->all())->toBe([
            [$this->acme->id, 'fornecimento', 120_000_000, 2024, 2030, true],
            [$this->acme->id, 'prestacao_servicos', 45_000_000, 2025, 2033, false],
            [$this->beta->id, 'prestacao_servicos', 8_000_050, 2026, 2028, false],
            [$this->beta->id, 'locacao', 9_500_000, 2022, 2031, false],
            [$this->beta->id, 'distribuicao', 12_000_000, 2023, 2027, true],
            [$this->beta->id, 'manutencao', 6_000_000, 2026, 2029, false],
        ])
            ->and($rows->pluck('firm_id')->unique()->all())->toBe([FiscalRows::francoId()])
            ->and($rows->pluck('clause_marked_at')->filter()->all())->toBe([]);
    });

    it('reads a comma-separated file with a BOM, quoted fields and the columns in another order', function () {
        importContracts(contractsCsv(
            ['"2030","11.222.333/0001-81","1.200.000,00","Fornecimento","2024","sim"'],
            'fim,cnpj_cliente,valor_anual,tipo,inicio,clausula_reforma,observacao',
            "\xEF\xBB\xBF",
        ))->assertOk()->assertJsonPath('data.created', 1);

        expect(ClientContract::withoutGlobalScopes()->sole()->only(['kind', 'annual_value_cents', 'start_year', 'end_year']))
            ->toBe(['kind' => 'fornecimento', 'annual_value_cents' => 120_000_000, 'start_year' => 2024, 'end_year' => 2030]);
    });

    it('updates one row when the same client, kind, start and end come twice — in one file or again', function () {
        importContracts(contractsCsv([
            '11222333000181;fornecimento;100000;2024;2030;nao',
            '11222333000181;Fornecimento;200000;2024;2030;sim',
        ]))->assertOk()->assertJsonPath('data.created', 1)->assertJsonPath('data.updated', 1);

        importContracts(contractsCsv(['11222333000181;fornecimento;300000;2024;2030;nao']))
            ->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.updated', 1);

        $row = ClientContract::withoutGlobalScopes()->sole();
        expect($row->annual_value_cents)->toBe(30_000_000)->and($row->has_reforma_clause)->toBeFalse();
    });

    it('reads an empty clause as "não" on a new row and keeps the stored one on an update (R18)', function () {
        importContracts(contractsCsv([
            '11222333000181;fornecimento;100000;2024;2030;sim',
            '33444555000181;locacao;100000;2024;2030;',
        ]))->assertOk();

        importContracts(contractsCsv(['11222333000181;fornecimento;150000;2024;2030;']))->assertOk();

        expect(ClientContract::withoutGlobalScopes()->where('client_id', $this->acme->id)->sole()->has_reforma_clause)->toBeTrue()
            ->and(ClientContract::withoutGlobalScopes()->where('client_id', $this->beta->id)->sole()->has_reforma_clause)->toBeFalse();
    });
});

describe('a clause the file set (review A3, W114)', function () {
    it('is the file\'s to correct: "sim" stamps no mark, and a later "não" takes it back', function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;sim']))->assertOk();

        expect(ClientContract::withoutGlobalScopes()->sole()->clause_marked_at)->toBeNull();

        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;não']))->assertOk();

        expect(ClientContract::withoutGlobalScopes()->sole()->has_reforma_clause)->toBeFalse();
    });
});

describe('a contract marked through "Cláusula incluída" (AM16)', function () {
    beforeEach(function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;']))->assertOk();
        $this->contract = ClientContract::withoutGlobalScopes()->sole();
        $this->actingAs(FiscalRows::person('gestao'))->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();
    });

    it('stays marked when re-imported with an empty clause', function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;180000;2024;2030;']))->assertOk()->assertJsonPath('data.updated', 1);

        $row = $this->contract->fresh();
        expect($row->has_reforma_clause)->toBeTrue()
            ->and($row->clause_marked_at)->not->toBeNull()
            ->and($row->annual_value_cents)->toBe(18_000_000);
    });

    it('stays marked when re-imported with "não"', function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;não']))->assertOk();

        expect($this->contract->fresh()->has_reforma_clause)->toBeTrue();
    });
});

describe('bad lines are named, good lines stored (A16, R18)', function () {
    it('names an unknown CNPJ, a year 1999, an end before the start and every other bad value by line', function () {
        $theirs = FiscalRows::client(['cnpj' => '44555666000181'], Firm::factory()->create(['slug' => 'firma-b'])->id);

        $response = importContracts(contractsCsv([
            '11222333000181;fornecimento;100000;2024;2030;sim',
            '99888777000166;fornecimento;100000;2024;2030;sim',
            '11222333000181;locacao;100000;1999;2030;sim',
            '11222333000181;manutencao;100000;2031;2029;nao',
            '11222333000181;aluguel;100000;2024;2030;nao',
            '11222333000181;distribuicao;cem mil;2024;2030;talvez',
            '44555666000181;fornecimento;100000;2024;2030;sim',
            '11222333000181;distribuicao;100000;2024;2101;',
            '11222333000181;distribuicao;0;2024;2030;',
            '11222333000181;locacao',
            '33444555000181;Locação;95000;2022;2031;',
        ]))->assertOk();

        expect($response->json('data.errors'))->toBe([
            ['line' => 3, 'field' => 'cnpj_cliente', 'code' => 'client_unknown'],
            ['line' => 4, 'field' => 'inicio', 'code' => 'year_invalid'],
            ['line' => 5, 'field' => 'fim', 'code' => 'end_before_start'],
            ['line' => 6, 'field' => 'tipo', 'code' => 'kind_invalid'],
            ['line' => 7, 'field' => 'valor_anual', 'code' => 'value_invalid'],
            ['line' => 7, 'field' => 'clausula_reforma', 'code' => 'clause_invalid'],
            ['line' => 8, 'field' => 'cnpj_cliente', 'code' => 'client_unknown'],
            ['line' => 9, 'field' => 'fim', 'code' => 'year_invalid'],
            ['line' => 10, 'field' => 'valor_anual', 'code' => 'value_invalid'],
            ['line' => 11, 'field' => null, 'code' => 'columns'],
        ])
            ->and($response->json('data'))->toMatchArray(['created' => 2, 'updated' => 0, 'total' => 11, 'total_errors' => 10])
            ->and(ClientContract::withoutGlobalScopes()->pluck('client_id')->all())->toBe([$this->acme->id, $this->beta->id])
            ->and(ClientContract::withoutGlobalScopes()->where('client_id', $theirs->id)->exists())->toBeFalse();
    });

    it('stores nothing and says so when every line is bad', function () {
        importContracts(contractsCsv(['99888777000166;fornecimento;100000;2024;2030;sim']))
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.total_errors', 1);

        expect(ClientContract::withoutGlobalScopes()->count())->toBe(0);
    });

    it('lists the first 100 problems and counts them all', function () {
        $lines = array_fill(0, 120, '99888777000166;fornecimento;100000;2024;2030;sim');

        $response = importContracts(contractsCsv($lines))->assertOk();

        expect($response->json('data.errors'))->toHaveCount(100)->and($response->json('data.total_errors'))->toBe(120);
    });

    it('never stores a formula: every stored value is a number, a year or a kind', function () {
        importContracts(contractsCsv(['=HYPERLINK(1);=1+1;=SUM(A1);=2024;@2030;+sim']))
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.total_errors', 6);
    });
});

describe('a file it refuses whole', function () {
    it('refuses a header without the template columns, naming them', function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030'], 'cnpj_cliente;tipo;valor_anual;inicio;fim'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_header')
            ->assertJsonPath('missing', ['clausula_reforma']);
    });

    it('refuses a file with no rows and a file that is not UTF-8', function () {
        importContracts(contractsCsv([]))->assertUnprocessable()->assertJsonPath('code', 'import_empty');

        $latin1 = UploadedFile::fake()->createWithContent('contratos.csv', CONTRACTS_CSV_HEADER."\n".mb_convert_encoding('11222333000181;Locação;100000;2024;2030;não', 'ISO-8859-1', 'UTF-8'));
        importContracts($latin1)->assertUnprocessable()->assertJsonPath('code', 'import_encoding');
    });

    it('refuses more than 5000 lines and stores none', function () {
        $lines = array_fill(0, 5001, '11222333000181;fornecimento;100000;2024;2030;sim');

        importContracts(contractsCsv($lines))->assertUnprocessable()->assertJsonPath('code', 'import_too_many')->assertJsonPath('limit', 5000);

        expect(ClientContract::withoutGlobalScopes()->count())->toBe(0);
    });

    it('asks for a csv file of up to 2 MB', function () {
        importContracts(UploadedFile::fake()->create('contratos.csv', 2049, 'text/csv'))->assertUnprocessable()->assertJsonValidationErrors('file');
        importContracts(UploadedFile::fake()->createWithContent('contratos.exe', 'x'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs(FiscalRows::person('gestao'))->postJson('/api/contracts/import')->assertUnprocessable()->assertJsonValidationErrors('file');
    });
});

describe('the template (R18)', function () {
    it('downloads the header row, with a BOM so a spreadsheet opens it as UTF-8', function () {
        $response = $this->actingAs(FiscalRows::person('gestao'))->get('/api/contracts/template')->assertOk();

        expect($response->streamedContent())->toBe("\xEF\xBB\xBF".CONTRACTS_CSV_HEADER."\n")
            ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
            ->and($response->headers->get('Content-Disposition'))->toContain('modelo-contratos.csv');
    });
});

describe('who reaches it', function () {
    it('throttles the import at 10 a minute', function () {
        $reinaldo = FiscalRows::person('gestao');
        $statuses = collect(range(1, 11))->map(fn () => importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;sim']), $reinaldo)->status());

        expect($statuses->take(10)->unique()->all())->toBe([200])->and($statuses->last())->toBe(429);
    });

    it('lets the operator import and refuses the team', function () {
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;sim']), 'operador')->assertOk();
        importContracts(contractsCsv(['11222333000181;fornecimento;100000;2024;2030;sim']), 'equipe')->assertForbidden();
    });
});
