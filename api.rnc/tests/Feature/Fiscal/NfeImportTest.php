<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Fiscal\Services\NfeImporter;
use App\Modules\Partner\Services\PartnerAccessService;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\GeneratedNfeSource;
use Database\Seeders\Support\NfeXmlBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\NfeFixture;

/*
 * The NF-e upload (rnc-2 F6a, R15–R17, A13, A14's XML half, A15; plan AM3, AM4 / spec C1):
 *
 *   POST /api/fiscal/imports   multipart `file` (one .xml, ≤ 20 MB, ≤ 1 MB read) → 201 the batch
 *                              `{id, source, user_id, files, stored, refused: [{file, code}], at}`
 *
 * Behind `fiscal.import` (ceo, operador), throttled 10 a minute (`nfe-import`). Each document is
 * matched by CNPJ to the firm's clients: emitted by one → `saida`, addressed to one → `entrada`, both
 * → two rows. The raw file goes to the private disk at `nfe/<firm id>/<client id>/<key>.xml`.
 */
const NFE_SELLER_CNPJ = '11222333000181';

const NFE_BUYER_CNPJ = '22333444000181';

const NFE_STRANGER_CNPJ = '33444555000181';

function nfeImportFirmId(): int
{
    return (int) Firm::where('slug', 'franco')->value('id');
}

function nfeImportPerson(string $role, ?int $firmId = null): User
{
    return User::factory()->create([
        'firm_id' => $firmId ?? nfeImportFirmId(),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function nfeImportClient(string $cnpj, ?int $firmId = null): Client
{
    return Client::withoutGlobalScopes()->create([
        'firm_id' => $firmId ?? nfeImportFirmId(),
        'name' => "Cliente {$cnpj} Ltda",
        'cnpj' => $cnpj,
        'sector' => 'comercio',
        'regime' => 'presumido',
        'band' => 'b3',
        'b2b_share' => 70,
        'monthly_fee' => 3000,
    ]);
}

function nfeImportFile(string $content, string $name = 'nota.xml'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function nfeImportUpload(User $person, UploadedFile $file): TestResponse
{
    return test()->actingAs($person)->post('/api/fiscal/imports', ['file' => $file], ['Accept' => 'application/json']);
}

/** @return list<NfeDocument> */
function nfeImportDocuments(): array
{
    return NfeDocument::withoutGlobalScopes()->orderBy('id')->get()->all();
}

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
});

describe('a client\'s sale (A13)', function () {
    it('stores the key, the CRT, the recipient and the item checks as a saída', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);
        $person = nfeImportPerson('ceo');
        $xml = NfeXmlBuilder::nfe(['crt' => 3, 'recipient_cpf' => '99900000191', 'total' => '980.10', 'items' => [[], ['group' => false], ['cst' => '']]]);

        $data = nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->json('data');

        $documents = nfeImportDocuments();
        $key = NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123);
        $path = 'nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.$key.'.xml';

        expect($data)->toMatchArray(['source' => 'upload', 'user_id' => $person->id, 'files' => 1, 'stored' => 1, 'refused' => []])
            ->and($data['at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/')
            ->and($documents)->toHaveCount(1)
            ->and($documents[0])->toMatchArray([
                'firm_id' => nfeImportFirmId(),
                'client_id' => $seller->id,
                'import_id' => $data['id'],
                'direction' => 'saida',
                'access_key' => $key,
                'number' => 123,
                'series' => 1,
                'emitter_cnpj' => NFE_SELLER_CNPJ,
                'emitter_crt' => 3,
                'recipient_cnpj' => null,
                'recipient_kind' => 'consumidor',
                'total_cents' => 98_010,
                'system' => 'ERP Exemplo 4.2',
                'items' => 3,
                'items_missing_group' => 1,
                'items_missing_codes' => 1,
                'items_without_test_values' => 0,
                'sample' => ['missing_group' => [2], 'missing_codes' => [3], 'without_test_values' => []],
                'path' => $path,
            ])
            ->and($documents[0]->issued_at->toIso8601String())->toBe('2026-10-15T13:30:00+00:00')
            ->and(Storage::disk('local')->get($path))->toBe($xml)
            ->and(NfeImport::withoutGlobalScopes()->count())->toBe(1);
    });

    it('stores a purchase addressed to the client as an entrada', function () {
        $buyer = nfeImportClient(NFE_BUYER_CNPJ);

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(['emitter' => NFE_STRANGER_CNPJ, 'recipient' => NFE_BUYER_CNPJ, 'crt' => 1])))
            ->assertCreated()
            ->assertJsonPath('data.stored', 1);

        expect(nfeImportDocuments()[0])->toMatchArray([
            'client_id' => $buyer->id,
            'direction' => 'entrada',
            'emitter_cnpj' => NFE_STRANGER_CNPJ,
            'emitter_crt' => 1,
            'recipient_cnpj' => NFE_BUYER_CNPJ,
            'recipient_kind' => 'cnpj',
        ]);
    });

    it('refuses a document of no client as client_unknown', function () {
        nfeImportClient(NFE_SELLER_CNPJ);

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(['emitter' => NFE_STRANGER_CNPJ, 'recipient' => '44555666000181']), 'fora.xml'))
            ->assertCreated()
            ->json('data');

        expect($data)->toMatchArray(['files' => 1, 'stored' => 0, 'refused' => [['file' => 'fora.xml', 'code' => 'client_unknown']]])
            ->and(nfeImportDocuments())->toBe([])
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('keeps the same file uploaded twice as one document and one duplicate', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $person = nfeImportPerson('ceo');

        nfeImportUpload($person, nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated();
        $second = nfeImportUpload($person, nfeImportFile(NfeXmlBuilder::nfe(), 'de-novo.xml'))->assertCreated()->json('data');

        expect($second)->toMatchArray(['stored' => 0, 'refused' => [['file' => 'de-novo.xml', 'code' => 'duplicate']]])
            ->and(nfeImportDocuments())->toHaveCount(1)
            ->and(NfeImport::withoutGlobalScopes()->count())->toBe(2);
    });

    it('refuses a bad check digit as invalid_key', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $broken = NfeFixture::brokenKey(NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123));

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(['key' => $broken])))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'nota.xml', 'code' => 'invalid_key']]);

        expect(nfeImportDocuments())->toBe([]);
    });

    it('stores a document between two clients twice, and its re-upload reports a duplicate per row (AM4)', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);
        $buyer = nfeImportClient(NFE_BUYER_CNPJ);
        $person = nfeImportPerson('ceo');
        $xml = NfeXmlBuilder::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_BUYER_CNPJ]);

        $first = nfeImportUpload($person, nfeImportFile($xml, 'entre.xml'))->assertCreated()->json('data');
        $again = nfeImportUpload($person, nfeImportFile($xml, 'entre.xml'))->assertCreated()->json('data');

        $rows = collect(nfeImportDocuments())->map(fn (NfeDocument $row) => [$row->client_id, $row->direction])->all();

        expect($first)->toMatchArray(['stored' => 2, 'refused' => []])
            ->and($rows)->toBe([[$seller->id, 'saida'], [$buyer->id, 'entrada']])
            ->and($again)->toMatchArray(['stored' => 0, 'refused' => [
                ['file' => 'entre.xml', 'code' => 'duplicate'],
                ['file' => 'entre.xml', 'code' => 'duplicate'],
            ]])
            ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
    });

    it('stores only the missing row when one of the two clients already has the document', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $person = nfeImportPerson('ceo');
        $xml = NfeXmlBuilder::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_BUYER_CNPJ]);

        nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->assertJsonPath('data.stored', 1);
        nfeImportClient(NFE_BUYER_CNPJ);
        $again = nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->json('data');

        expect($again)->toMatchArray(['stored' => 1, 'refused' => [['file' => 'nota.xml', 'code' => 'duplicate']]])
            ->and(nfeImportDocuments())->toHaveCount(2);
    });

    it('stores one saída when the client is both the emitter and the recipient', function () {
        nfeImportClient(NFE_SELLER_CNPJ);

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_SELLER_CNPJ])))
            ->assertCreated()
            ->assertJsonPath('data.stored', 1)
            ->assertJsonPath('data.refused', []);

        expect(nfeImportDocuments()[0]->direction)->toBe('saida');
    });
});

describe('a file that cannot be read (A14, plan AM3)', function () {
    it('never reads an external entity\'s target: its content is nowhere — row, file, refusal, answer', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $target = tempnam(sys_get_temp_dir(), 'nfe-xxe');
        file_put_contents($target, 'CANARY-XXE-5c2e08');

        try {
            $response = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::withExternalEntity('file://'.$target), 'entidade.xml'))->assertCreated();
        } finally {
            unlink($target);
        }

        $stored = implode('', array_map(fn (string $file) => (string) Storage::disk('local')->get($file), Storage::disk('local')->allFiles()));
        $batch = NfeImport::withoutGlobalScopes()->firstOrFail();

        expect($response->json('data.refused'))->toBe([['file' => 'entidade.xml', 'code' => 'not_nfe']])
            ->and(nfeImportDocuments())->toBe([])
            ->and($response->getContent())->not->toContain('CANARY')
            ->and(json_encode($batch->getAttributes()))->not->toContain('CANARY')
            ->and($stored)->not->toContain('CANARY');
    });

    it('refuses the file:///etc/hostname entity as not_nfe', function () {
        nfeImportClient(NFE_SELLER_CNPJ);

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::withExternalEntity('file:///etc/hostname')))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'nota.xml', 'code' => 'not_nfe']]);

        expect(nfeImportDocuments())->toBe([]);
    });

    it('refuses the billion-laughs file as not_nfe', function () {
        nfeImportClient(NFE_SELLER_CNPJ);

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::billionLaughs(), 'risos.xml'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'risos.xml', 'code' => 'not_nfe']]);
    });

    it('refuses a .xml whose content is not XML as not_xml', function () {
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile('isto não é uma nota', 'texto.xml'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'texto.xml', 'code' => 'not_xml']]);
    });

    it('refuses an XML over 1 MB as too_large and stores nothing', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $padded = str_replace('</nfeProc>', str_repeat(' ', 1_048_576).'</nfeProc>', NfeXmlBuilder::nfe());

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile($padded, 'grande.xml'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'grande.xml', 'code' => 'too_large']]);

        expect(nfeImportDocuments())->toBe([]);
    });
});

describe('a ZIP of XMLs (A14, plan AM3)', function () {
    it('stores the good XML, refuses the non-XML and the entity by file, and never reads the entity\'s target', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);
        $target = tempnam(sys_get_temp_dir(), 'nfe-xxe');
        file_put_contents($target, 'CANARY-XXE-zip-7d41');

        try {
            $zip = NfeFixture::zip([
                'notas/boa.xml' => NfeXmlBuilder::nfe(),
                'notas/texto.xml' => 'isto não é uma nota',
                'notas/entidade.xml' => NfeFixture::withExternalEntity('file://'.$target),
            ]);
            $response = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile($zip, 'lote.zip'))->assertCreated();
        } finally {
            unlink($target);
        }

        $stored = implode('', array_map(fn (string $file) => (string) Storage::disk('local')->get($file), Storage::disk('local')->allFiles()));

        expect($response->json('data'))->toMatchArray(['source' => 'upload', 'files' => 3, 'stored' => 1, 'refused' => [
            ['file' => 'texto.xml', 'code' => 'not_xml'],
            ['file' => 'entidade.xml', 'code' => 'not_nfe'],
        ]])
            ->and(nfeImportDocuments())->toHaveCount(1)
            ->and(nfeImportDocuments()[0]->client_id)->toBe($seller->id)
            ->and($response->getContent())->not->toContain('CANARY')
            ->and(json_encode(NfeImport::withoutGlobalScopes()->firstOrFail()->getAttributes()))->not->toContain('CANARY')
            ->and($stored)->not->toContain('CANARY');
    });

    it('skips directories, nested ZIPs, other names and macOS shadows without counting them', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $inner = NfeFixture::zip(['dentro.xml' => NfeXmlBuilder::nfe(['number' => 2])]);

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::zip([
            'pasta/' => '',
            'pasta/nota.XML' => NfeXmlBuilder::nfe(),
            'dentro.zip' => $inner,
            'leia-me.txt' => NfeXmlBuilder::nfe(['number' => 3]),
            '__MACOSX/pasta/._nota.XML' => "\0\5\26\7binary",
            '._outra.xml' => "\0\5\26\7binary",
        ]), 'lote.zip'))->assertCreated()->json('data');

        expect($data)->toMatchArray(['files' => 1, 'stored' => 1, 'refused' => []])
            ->and(nfeImportDocuments())->toHaveCount(1)
            ->and(nfeImportDocuments()[0]->number)->toBe(123);
    });

    it('labels an entry named with ../ by its base name and stores it by key and client id only', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::zip([
            '../../../public/evil.xml' => NfeXmlBuilder::nfe(),
            '..\\..\\windows\\ruim.xml' => 'não é xml',
        ]), 'lote.zip'))->assertCreated()->json('data');

        expect($data['refused'])->toBe([['file' => 'ruim.xml', 'code' => 'not_xml']])
            ->and(Storage::disk('local')->allFiles())->toBe(['nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123).'.xml'])
            ->and(file_exists(base_path('public/evil.xml')))->toBeFalse();
    });

    it('refuses an entry that inflates past 1 MB as too_large, reading no more than 1 MB of it (zip bomb)', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $bomb = NfeFixture::zip(['bomba.xml' => str_repeat(' ', 64 * 1_048_576), 'boa.xml' => NfeXmlBuilder::nfe()]);
        $file = nfeImportFile($bomb, 'lote.zip');
        $person = nfeImportPerson('ceo');

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $data = nfeImportUpload($person, $file)->assertCreated()->json('data');
        $peak = memory_get_peak_usage() - $before;

        expect($data)->toMatchArray(['files' => 2, 'stored' => 1, 'refused' => [['file' => 'bomba.xml', 'code' => 'too_large']]])
            ->and(strlen($bomb))->toBeLessThan(200_000)
            ->and($peak)->toBeLessThan(16 * 1_048_576);
    });

    it('never trusts the size an entry declares: a 64 MB entry claiming 100 bytes is read to 1 MB and refused', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $forged = NfeFixture::zipWithForgedSize('mentira.xml', str_repeat(' ', 64 * 1_048_576), 100);
        $file = nfeImportFile($forged, 'lote.zip');
        $person = nfeImportPerson('ceo');

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $data = nfeImportUpload($person, $file)->assertCreated()->json('data');
        $peak = memory_get_peak_usage() - $before;

        expect($data)->toMatchArray(['files' => 1, 'stored' => 0, 'refused' => [['file' => 'mentira.xml', 'code' => 'too_large']]])
            ->and($peak)->toBeLessThan(16 * 1_048_576);
    });

    it('stops reading at 20 MB for the whole archive: every later entry is too_large', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $entries = [];

        for ($number = 1; $number <= 21; $number++) {
            $xml = NfeXmlBuilder::nfe(['number' => $number]);
            $entries["nota-{$number}.xml"] = str_replace('</nfeProc>', str_repeat(' ', 1_040_000 - strlen($xml)).'</nfeProc>', $xml);
        }

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::zip($entries), 'grande.zip'))->assertCreated()->json('data');

        expect($data)->toMatchArray(['files' => 21, 'stored' => 20, 'refused' => [['file' => 'nota-21.xml', 'code' => 'too_large']]]);
    });

    it('refuses an archive of more than 2000 entries whole, and a .zip that is not one', function () {
        $entries = [];

        for ($number = 1; $number <= 2001; $number++) {
            $entries["n{$number}.txt"] = '';
        }

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::zip($entries), 'muitos.zip'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'muitos.zip', 'code' => 'too_large']]);
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(), 'falso.zip'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'falso.zip', 'code' => 'not_xml']]);
    });
});

describe('the one code path (R15)', function () {
    it('stamps the firm itself, so the seeders\' WithoutModelEvents cannot leave a row without one', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);
        $franco = Firm::where('slug', 'franco')->firstOrFail();
        $source = new GeneratedNfeSource(['gerada.xml' => NfeXmlBuilder::nfe()]);

        $import = Model::withoutEvents(fn () => app(CurrentFirm::class)->runAs(
            $franco,
            fn () => app(NfeImporter::class)->import($source, NfeImportSource::Folder, null),
        ));

        expect($import->only(['firm_id', 'files', 'stored', 'refused']))->toBe(['firm_id' => $franco->id, 'files' => 1, 'stored' => 1, 'refused' => []])
            ->and(nfeImportDocuments()[0]->only(['firm_id', 'client_id']))->toBe(['firm_id' => $franco->id, 'client_id' => $seller->id]);
    });
});

describe('the batch list (R17)', function () {
    it('lists the firm\'s batches newest first, with who, how and every refusal', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $reinaldo = User::where('username', 'reinaldo')->firstOrFail();
        $empty = sys_get_temp_dir().'/nfe-vazia-'.bin2hex(random_bytes(4));
        mkdir($empty);

        CarbonImmutable::setTestNow('2026-11-02 09:00:00');
        nfeImportUpload($reinaldo, nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated();
        CarbonImmutable::setTestNow('2026-11-03 09:00:00');
        Artisan::call('nfe:import', ['folder' => $empty, '--firm' => 'franco']);
        rmdir($empty);
        CarbonImmutable::setTestNow('2026-11-04 09:00:00');
        nfeImportUpload($reinaldo, nfeImportFile(NfeXmlBuilder::nfe(), 'de-novo.xml'))->assertCreated();
        $otherFirm = Firm::factory()->create();
        nfeImportUpload(nfeImportPerson('ceo', $otherFirm->id), nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated();
        CarbonImmutable::setTestNow();

        $page = test()->actingAs($reinaldo)->getJson('/api/fiscal/imports')->assertOk()->json('data');

        expect($page['total'])->toBe(3)
            ->and(array_column($page['data'], 'at'))->toBe(['2026-11-04T12:00:00+00:00', '2026-11-03T12:00:00+00:00', '2026-11-02T12:00:00+00:00'])
            ->and($page['data'][0])->toMatchArray(['source' => 'upload', 'user_id' => $reinaldo->id, 'by_name' => 'Reinaldo', 'files' => 1, 'stored' => 0, 'refused' => [['file' => 'de-novo.xml', 'code' => 'duplicate']]])
            ->and($page['data'][1])->toMatchArray(['source' => 'folder', 'user_id' => null, 'by_name' => null])
            ->and($page['data'][2])->toMatchArray(['stored' => 1, 'refused' => []])
            ->and(json_encode($page))->not->toContain('nfe/');
    });

    it('pages the list and answers the upload with the same shape', function () {
        $reinaldo = User::where('username', 'reinaldo')->firstOrFail();

        $created = nfeImportUpload($reinaldo, nfeImportFile('x', 'texto.xml'))->assertCreated()->json('data');
        nfeImportUpload($reinaldo, nfeImportFile('x', 'texto.xml'))->assertCreated();

        $page = test()->actingAs($reinaldo)->getJson('/api/fiscal/imports?per_page=1&page=2')->assertOk()->json('data');

        expect($created)->toMatchArray(['by_name' => 'Reinaldo'])
            ->and($page['total'])->toBe(2)
            ->and($page['data'])->toHaveCount(1)
            ->and($page['data'][0])->toBe($created);
        test()->actingAs($reinaldo)->getJson('/api/fiscal/imports?per_page=101')->assertUnprocessable();
    });

    it('is the importer\'s: the ceo and the operator read it, the team and a partner do not', function () {
        test()->getJson('/api/fiscal/imports')->assertUnauthorized();

        test()->actingAs(nfeImportPerson('ceo'))->getJson('/api/fiscal/imports')->assertOk();
        test()->actingAs(nfeImportPerson('operador'))->getJson('/api/fiscal/imports')->assertOk();
        test()->actingAs(nfeImportPerson('equipe'))->getJson('/api/fiscal/imports')->assertForbidden();
        test()->actingAs(nfeImportPerson(PartnerAccessService::PARTNER_ROLE_SLUG))->getJson('/api/fiscal/imports')->assertForbidden();
    });
});

describe('the upload itself', function () {
    it('refuses an upload over 20 MB before reading it', function () {
        nfeImportUpload(nfeImportPerson('ceo'), UploadedFile::fake()->create('enorme.xml', 20 * 1024 + 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        expect(NfeImport::withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses a missing file or another extension', function (?string $name) {
        $payload = $name === null ? [] : ['file' => nfeImportFile(NfeXmlBuilder::nfe(), $name)];

        test()->actingAs(nfeImportPerson('ceo'))->post('/api/fiscal/imports', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    })->with([
        'no file' => [null],
        'a pdf' => ['nota.pdf'],
    ]);

    it('names a refusal by the file\'s base name and stores by key and client id only', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(['key' => NfeFixture::brokenKey(NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123))]), '../../etc/passwd.xml'))
            ->assertCreated()
            ->json('data');
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe(), '../../../public/evil.xml'))->assertCreated();

        expect($data['refused'][0]['file'])->toBe('passwd.xml')
            ->and(Storage::disk('local')->allFiles())->toBe(['nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123).'.xml']);
    });
});

describe('who may import', function () {
    it('lets the ceo and the operator import, refuses the team and a guest', function () {
        test()->post('/api/fiscal/imports', ['file' => nfeImportFile(NfeXmlBuilder::nfe())], ['Accept' => 'application/json'])->assertUnauthorized();

        nfeImportUpload(nfeImportPerson('operador'), nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated();
        nfeImportUpload(nfeImportPerson('equipe'), nfeImportFile(NfeXmlBuilder::nfe()))->assertForbidden();
        nfeImportUpload(nfeImportPerson(PartnerAccessService::PARTNER_ROLE_SLUG), nfeImportFile(NfeXmlBuilder::nfe()))->assertForbidden();
    });

    it('throttles the upload at 10 a minute', function () {
        $person = nfeImportPerson('ceo');

        $statuses = collect(range(1, 11))->map(fn () => nfeImportUpload($person, nfeImportFile('x', 'texto.xml'))->status());

        expect($statuses->take(10)->unique()->all())->toBe([201])
            ->and($statuses->last())->toBe(429);
    });
});

describe('private storage and the firm wall (A15)', function () {
    it('serves the raw XML at no URL and never names its path', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);

        $response = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated();
        $path = 'nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.NfeXmlBuilder::key(NFE_SELLER_CNPJ, 123).'.xml';

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'nfe') || str_contains($route->uri(), 'fiscal'))
            ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
            ->values()
            ->all();

        expect(Storage::disk('local')->exists($path))->toBeTrue()
            ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
            ->and($response->getContent())->not->toContain('nfe/')
            ->and($routes)->toEqualCanonicalizing(['POST api/fiscal/imports', 'GET api/fiscal/imports'])
            ->and(test()->get('/storage/'.$path)->status())->not->toBe(200)
            ->and(test()->actingAs(nfeImportPerson('ceo'))->get('/storage/'.$path)->status())->not->toBe(200)
            ->and(test()->get('/storage/app/private/'.$path)->status())->not->toBe(200);
    });

    it('matches only the importing firm\'s clients, and each firm sees only its documents', function () {
        $otherFirm = Firm::factory()->create();
        nfeImportClient(NFE_SELLER_CNPJ, $otherFirm->id);
        $otherPerson = nfeImportPerson('ceo', $otherFirm->id);

        nfeImportUpload($otherPerson, nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated()->assertJsonPath('data.stored', 1);
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe()))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'nota.xml', 'code' => 'client_unknown']]);

        $ours = nfeImportClient(NFE_SELLER_CNPJ);
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeXmlBuilder::nfe()))->assertCreated()->assertJsonPath('data.stored', 1);

        $franco = Firm::where('slug', 'franco')->firstOrFail();
        $currentFirm = app(CurrentFirm::class);

        expect($currentFirm->runAs($franco, fn () => NfeDocument::query()->pluck('client_id')->all()))->toBe([$ours->id])
            ->and($currentFirm->runAs($otherFirm, fn () => NfeDocument::query()->count()))->toBe(1)
            ->and($currentFirm->runAs($franco, fn () => NfeImport::query()->count()))->toBe(2)
            ->and(NfeDocument::withoutGlobalScopes()->distinct()->pluck('access_key')->all())->toHaveCount(1);
    });
});
