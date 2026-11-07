<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

/*
 * The client's partner consent through the portfolio CSV (rnc-2 F9, R28, A22; plan AM11):
 *
 *   POST /api/clients/import   the optional 8th column `autoriza_parceiros`
 *
 * `sim` → consent true, `não` → false, empty → none (null); read in any case and with or without
 * the accent (`SIM`, `Sim`, `nao`, `NÃO`, `Não`). A value given is stamped `via = contrato` with
 * the moment it was imported; anything else is a line error `consent_invalid`. A 7-column CSV
 * still imports and leaves the consent the client already had.
 */
const CONSENT_CSV_HEADER = 'nome;cnpj;setor;regime;faixa_faturamento;percentual_b2b;mensalidade;autoriza_parceiros';

function consentImporter(): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', 'ceo')->value('id'),
    ]);
}

/** @param  list<string>  $lines */
function consentCsv(array $lines, string $header = CONSENT_CSV_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('clientes.csv', implode("\n", [$header, ...$lines])."\n");
}

/** One valid line for the n-th invented CNPJ, with `$consent` as the 8th column. */
function consentLine(string $cnpj, string $consent): string
{
    return "Cliente {$cnpj} Ltda;{$cnpj};servicos;presumido;b3;60;3000;{$consent}";
}

function consentOf(string $cnpj): Client
{
    return Client::withoutGlobalScopes()->where('cnpj', $cnpj)->firstOrFail();
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->importer = consentImporter();
});

describe('the autoriza_parceiros column (A22)', function () {
    it('imports sim / não / empty as consent true / false / none', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([
            consentLine('11222333000181', 'sim'),
            consentLine('22333444000181', 'não'),
            consentLine('33444555000181', ''),
        ])], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 3);

        $yes = consentOf('11222333000181');
        $no = consentOf('22333444000181');
        $none = consentOf('33444555000181');

        expect($yes->partner_consent)->toBeTrue()
            ->and($yes->partner_consent_via)->toBe('contrato')
            ->and($yes->partner_consent_at?->utc()->toIso8601String())->toBe(CarbonImmutable::parse('2026-11-09 16:00:00Z')->toIso8601String())
            ->and($no->partner_consent)->toBeFalse()
            ->and($no->partner_consent_via)->toBe('contrato')
            ->and($no->partner_consent_at)->not->toBeNull()
            ->and($none->partner_consent)->toBeNull()
            ->and($none->partner_consent_via)->toBeNull()
            ->and($none->partner_consent_at)->toBeNull();
    });

    it('reads the value in any case, with or without the accent (AM11)', function (string $value, bool $expected) {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([consentLine('11222333000181', $value)])], ['Accept' => 'application/json'])->assertOk();

        expect(consentOf('11222333000181')->partner_consent)->toBe($expected);
    })->with([
        'sim' => ['sim', true],
        'SIM' => ['SIM', true],
        'Sim' => ['Sim', true],
        ' sim (spaces)' => ['  sim ', true],
        'não' => ['não', false],
        'nao' => ['nao', false],
        'NÃO' => ['NÃO', false],
        'Não' => ['Não', false],
        'NAO' => ['NAO', false],
    ]);

    it('names a value outside sim / não as a line error and imports nothing', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([
            consentLine('11222333000181', 'sim'),
            consentLine('22333444000181', 'talvez'),
            consentLine('33444555000181', 's'),
        ])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_invalid')
            ->assertJsonPath('errors', [
                ['line' => 3, 'field' => 'autoriza_parceiros', 'code' => 'consent_invalid'],
                ['line' => 4, 'field' => 'autoriza_parceiros', 'code' => 'consent_invalid'],
            ]);

        expect(Client::withoutGlobalScopes()->count())->toBe(0);
    });

    it('still imports a 7-column CSV, with no consent on a new client', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv(
            ['Atlas Ltda;11222333000181;servicos;simples;b2;80;1850'],
            'nome;cnpj;setor;regime;faixa_faturamento;percentual_b2b;mensalidade',
        )], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

        expect(consentOf('11222333000181')->partner_consent)->toBeNull();
    });
});

describe('a re-import', function () {
    it('keeps the consent a 7-column file does not mention', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([consentLine('11222333000181', 'sim')])], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv(
            ['Atlas Ltda;11222333000181;servicos;presumido;b3;60;3000'],
            'nome;cnpj;setor;regime;faixa_faturamento;percentual_b2b;mensalidade',
        )], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.updated', 1);

        $client = consentOf('11222333000181');

        expect($client->name)->toBe('Atlas Ltda')
            ->and($client->partner_consent)->toBeTrue()
            ->and($client->partner_consent_via)->toBe('contrato');
    });

    it('keeps the moment of an unchanged answer and stamps a changed one anew', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([
            consentLine('11222333000181', 'sim'),
            consentLine('22333444000181', 'sim'),
        ])], ['Accept' => 'application/json'])->assertOk();

        CarbonImmutable::setTestNow('2026-11-20 10:00:00');
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([
            consentLine('11222333000181', 'SIM'),
            consentLine('22333444000181', 'nao'),
        ])], ['Accept' => 'application/json'])->assertOk();

        expect(consentOf('11222333000181')->partner_consent_at?->utc()->toIso8601String())->toBe(CarbonImmutable::parse('2026-11-09 16:00:00Z')->toIso8601String())
            ->and(consentOf('22333444000181')->partner_consent)->toBeFalse()
            ->and(consentOf('22333444000181')->partner_consent_at?->utc()->toIso8601String())->toBe(CarbonImmutable::parse('2026-11-20 13:00:00Z')->toIso8601String());
    });

    it('clears the consent when the column is present and empty', function () {
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([consentLine('11222333000181', 'sim')])], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->importer)->post('/api/clients/import', ['file' => consentCsv([consentLine('11222333000181', '')])], ['Accept' => 'application/json'])->assertOk();

        $client = consentOf('11222333000181');

        expect($client->partner_consent)->toBeNull()
            ->and($client->partner_consent_at)->toBeNull()
            ->and($client->partner_consent_via)->toBeNull();
    });
});

describe('the template (R28)', function () {
    it('gains the autoriza_parceiros column, last', function () {
        $response = $this->actingAs($this->importer)->get('/api/clients/import/template')->assertOk();

        expect(trim($response->streamedContent()))->toBe("\xEF\xBB\xBF".CONSENT_CSV_HEADER);
    });
});
