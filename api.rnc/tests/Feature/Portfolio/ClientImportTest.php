<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\Client;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

/*
 * The client portfolio's CSV import (rnc-1 F8, R25, A13; plan AM8 `clients.import` — ceo, operador):
 *
 *   POST /api/clients/import            multipart `file` → `{created, updated, total}`, all or nothing
 *   GET  /api/clients/import/template   the header row to fill in
 *
 * UTF-8 (a BOM is dropped), `;` or `,` read from the header, CNPJ check digits validated, every
 * refused line named by its line number in the file; a CNPJ already in the firm is updated.
 */
const CLIENTS_CSV_HEADER = 'nome;cnpj;setor;regime;faixa_faturamento;percentual_b2b;mensalidade';

function importPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

/** @param  list<string>  $lines */
function clientsCsv(array $lines, string $header = CLIENTS_CSV_HEADER, string $prefix = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent('clientes.csv', $prefix.implode("\n", [$header, ...$lines])."\n");
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
});

describe('a good file (A13)', function () {
    it('imports every row for the firm', function () {
        $file = clientsCsv([
            'Atlas Soluções em TI Ltda;11.222.333/0001-81;servicos;simples;b2;80;1.850,00',
            'Metalúrgica Horizonte Ltda;22333444000181;Indústria;Lucro Real;b4;95;7200',
            'Clínica Bem Viver Ltda;33.444.555/0001-81;saude;presumido;b3;20%;3.800',
        ]);

        $data = $this->actingAs(importPerson('gestao'))->post('/api/clients/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk()->json('data');

        $atlas = Client::withoutGlobalScopes()->where('cnpj', '11222333000181')->first();
        $metal = Client::withoutGlobalScopes()->where('cnpj', '22333444000181')->first();

        expect($data)->toMatchArray(['created' => 3, 'updated' => 0, 'total' => 3])
            ->and(Client::withoutGlobalScopes()->count())->toBe(3)
            ->and($atlas)->toMatchArray(['name' => 'Atlas Soluções em TI Ltda', 'sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b_share' => 80])
            ->and((float) $atlas->monthly_fee)->toBe(1850.0)
            ->and($atlas->firm_id)->toBe(Firm::where('slug', 'franco')->value('id'))
            ->and($metal)->toMatchArray(['sector' => 'industria', 'regime' => 'real'])
            ->and((float) Client::withoutGlobalScopes()->where('cnpj', '33444555000181')->value('monthly_fee'))->toBe(3800.0);
    });

    it('reads a comma-separated file with a BOM', function () {
        $file = clientsCsv(
            ['"Empório Pinheiros Ltda",44.555.666/0001-81,comercio,simples,b2,10,"1.250,50"'],
            str_replace(';', ',', CLIENTS_CSV_HEADER),
            "\xEF\xBB\xBF",
        );

        $this->actingAs(importPerson('operador'))->post('/api/clients/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $client = Client::withoutGlobalScopes()->first();

        expect($client->name)->toBe('Empório Pinheiros Ltda')
            ->and((float) $client->monthly_fee)->toBe(1250.5);
    });

    it('updates a CNPJ the firm already has instead of adding it twice', function () {
        $person = importPerson('gestao');

        $this->actingAs($person)->post('/api/clients/import', ['file' => clientsCsv(['Atlas TI;11.222.333/0001-81;servicos;simples;b2;80;1850'])], ['Accept' => 'application/json'])->assertOk();

        $data = $this->actingAs($person)->post('/api/clients/import', ['file' => clientsCsv([
            'Atlas Soluções em TI Ltda;11222333000181;servicos;presumido;b3;70;2400',
            'AgroVale Grãos S.A.;55.666.777/0001-81;agro;real;b5;100;8000',
        ])], ['Accept' => 'application/json'])->assertOk()->json('data');

        expect($data)->toMatchArray(['created' => 1, 'updated' => 1, 'total' => 2])
            ->and(Client::withoutGlobalScopes()->count())->toBe(2)
            ->and(Client::withoutGlobalScopes()->where('cnpj', '11222333000181')->first())->toMatchArray(['name' => 'Atlas Soluções em TI Ltda', 'regime' => 'presumido', 'band' => 'b3']);
    });
});

describe('a bad file imports nothing (A13)', function () {
    it('names the line of the one bad row and leaves the portfolio as it was', function () {
        $file = clientsCsv([
            'Atlas Soluções em TI Ltda;11.222.333/0001-81;servicos;simples;b2;80;1850',
            'Metalúrgica Horizonte Ltda;22.333.444/0001-82;industria;real;b4;95;7200',
            'Clínica Bem Viver Ltda;33.444.555/0001-81;saude;presumido;b3;20;3800',
        ]);

        $response = $this->actingAs(importPerson('gestao'))->post('/api/clients/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_invalid');

        expect($response->json('errors'))->toBe([['line' => 3, 'field' => 'cnpj', 'code' => 'cnpj_invalid']])
            ->and(Client::withoutGlobalScopes()->count())->toBe(0);
    });

    it('reports every problem of every line', function () {
        $file = clientsCsv([
            ';11.222.333/0001-81;mineracao;mei;b9;180;abc',
            'Atlas Soluções em TI Ltda;11.222.333/0001-81;servicos;simples;b2;80;100',
            'Cópia;11222333000181;servicos;simples;b2;80;1850',
            'Curta;123',
        ]);

        $errors = $this->actingAs(importPerson('gestao'))->post('/api/clients/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->json('errors');

        expect($errors)->toBe([
            ['line' => 2, 'field' => 'nome', 'code' => 'required'],
            ['line' => 2, 'field' => 'setor', 'code' => 'sector_invalid'],
            ['line' => 2, 'field' => 'regime', 'code' => 'regime_invalid'],
            ['line' => 2, 'field' => 'faixa_faturamento', 'code' => 'band_invalid'],
            ['line' => 2, 'field' => 'percentual_b2b', 'code' => 'b2b_invalid'],
            ['line' => 2, 'field' => 'mensalidade', 'code' => 'fee_invalid'],
            ['line' => 3, 'field' => 'cnpj', 'code' => 'cnpj_duplicate'],
            ['line' => 3, 'field' => 'mensalidade', 'code' => 'fee_invalid'],
            ['line' => 4, 'field' => 'cnpj', 'code' => 'cnpj_duplicate'],
            ['line' => 5, 'field' => null, 'code' => 'columns'],
        ])->and(Client::withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses a header that is not the template\'s', function () {
        $file = clientsCsv(['Atlas;11.222.333/0001-81;servicos;simples;b2;80;1850'], 'nome;cnpj;setor;regime;faixa;b2b;mensalidade');

        $this->actingAs(importPerson('gestao'))->post('/api/clients/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_header')
            ->assertJsonPath('missing', ['faixa_faturamento', 'percentual_b2b']);
    });

    it('refuses a file with no rows and a file that is not UTF-8', function () {
        $person = importPerson('gestao');

        $this->actingAs($person)->post('/api/clients/import', ['file' => clientsCsv([])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_empty');

        $latin1 = UploadedFile::fake()->createWithContent('clientes.csv', CLIENTS_CSV_HEADER."\n".mb_convert_encoding('Clínica;33.444.555/0001-81;saude;presumido;b3;20;3800', 'ISO-8859-1', 'UTF-8'));

        $this->actingAs($person)->post('/api/clients/import', ['file' => $latin1], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_encoding');
    });

    it('asks for a file', function () {
        $this->actingAs(importPerson('gestao'))->postJson('/api/clients/import', [])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    });
});

describe('the template (R25)', function () {
    it('downloads the header row, with a BOM so a spreadsheet opens it as UTF-8', function () {
        $response = $this->actingAs(importPerson('gestao'))->get('/api/clients/import/template')->assertOk();

        expect($response->headers->get('content-type'))->toContain('text/csv')
            ->and($response->headers->get('content-disposition'))->toContain('modelo-clientes.csv')
            // rnc-2 F9 (R28): the optional consent column closes the template.
            ->and(trim($response->streamedContent()))->toBe("\xEF\xBB\xBF".CLIENTS_CSV_HEADER.';autoriza_parceiros');
    });
});

describe('who reaches it (AM8)', function () {
    it('refuses the team with a 403', function () {
        $team = importPerson('equipe');

        $this->actingAs($team)->post('/api/clients/import', ['file' => clientsCsv(['Atlas;11.222.333/0001-81;servicos;simples;b2;80;1850'])], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($team)->getJson('/api/clients/import/template')->assertForbidden();

        expect(Client::withoutGlobalScopes()->count())->toBe(0);
    });
});
