<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Partner\Services\PartnerAccessService;
use App\Modules\Portfolio\Models\Client;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
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
        $xml = NfeFixture::nfe(['crt' => 3, 'recipient_cpf' => '99900000191', 'total' => '980.10', 'items' => [[], ['group' => false], ['cst' => '']]]);

        $data = nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->json('data');

        $documents = nfeImportDocuments();
        $key = NfeFixture::key(NFE_SELLER_CNPJ, 123);
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

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(['emitter' => NFE_STRANGER_CNPJ, 'recipient' => NFE_BUYER_CNPJ, 'crt' => 1])))
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

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(['emitter' => NFE_STRANGER_CNPJ, 'recipient' => '44555666000181']), 'fora.xml'))
            ->assertCreated()
            ->json('data');

        expect($data)->toMatchArray(['files' => 1, 'stored' => 0, 'refused' => [['file' => 'fora.xml', 'code' => 'client_unknown']]])
            ->and(nfeImportDocuments())->toBe([])
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('keeps the same file uploaded twice as one document and one duplicate', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $person = nfeImportPerson('ceo');

        nfeImportUpload($person, nfeImportFile(NfeFixture::nfe()))->assertCreated();
        $second = nfeImportUpload($person, nfeImportFile(NfeFixture::nfe(), 'de-novo.xml'))->assertCreated()->json('data');

        expect($second)->toMatchArray(['stored' => 0, 'refused' => [['file' => 'de-novo.xml', 'code' => 'duplicate']]])
            ->and(nfeImportDocuments())->toHaveCount(1)
            ->and(NfeImport::withoutGlobalScopes()->count())->toBe(2);
    });

    it('refuses a bad check digit as invalid_key', function () {
        nfeImportClient(NFE_SELLER_CNPJ);
        $broken = NfeFixture::brokenKey(NfeFixture::key(NFE_SELLER_CNPJ, 123));

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(['key' => $broken])))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'nota.xml', 'code' => 'invalid_key']]);

        expect(nfeImportDocuments())->toBe([]);
    });

    it('stores a document between two clients twice, and its re-upload reports a duplicate per row (AM4)', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);
        $buyer = nfeImportClient(NFE_BUYER_CNPJ);
        $person = nfeImportPerson('ceo');
        $xml = NfeFixture::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_BUYER_CNPJ]);

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
        $xml = NfeFixture::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_BUYER_CNPJ]);

        nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->assertJsonPath('data.stored', 1);
        nfeImportClient(NFE_BUYER_CNPJ);
        $again = nfeImportUpload($person, nfeImportFile($xml))->assertCreated()->json('data');

        expect($again)->toMatchArray(['stored' => 1, 'refused' => [['file' => 'nota.xml', 'code' => 'duplicate']]])
            ->and(nfeImportDocuments())->toHaveCount(2);
    });

    it('stores one saída when the client is both the emitter and the recipient', function () {
        nfeImportClient(NFE_SELLER_CNPJ);

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(['emitter' => NFE_SELLER_CNPJ, 'recipient' => NFE_SELLER_CNPJ])))
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
        $padded = str_replace('</nfeProc>', str_repeat(' ', 1_048_576).'</nfeProc>', NfeFixture::nfe());

        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile($padded, 'grande.xml'))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'grande.xml', 'code' => 'too_large']]);

        expect(nfeImportDocuments())->toBe([]);
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
        $payload = $name === null ? [] : ['file' => nfeImportFile(NfeFixture::nfe(), $name)];

        test()->actingAs(nfeImportPerson('ceo'))->post('/api/fiscal/imports', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    })->with([
        'no file' => [null],
        'a pdf' => ['nota.pdf'],
    ]);

    it('names a refusal by the file\'s base name and stores by key and client id only', function () {
        $seller = nfeImportClient(NFE_SELLER_CNPJ);

        $data = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(['key' => NfeFixture::brokenKey(NfeFixture::key(NFE_SELLER_CNPJ, 123))]), '../../etc/passwd.xml'))
            ->assertCreated()
            ->json('data');
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe(), '../../../public/evil.xml'))->assertCreated();

        expect($data['refused'][0]['file'])->toBe('passwd.xml')
            ->and(Storage::disk('local')->allFiles())->toBe(['nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.NfeFixture::key(NFE_SELLER_CNPJ, 123).'.xml']);
    });
});

describe('who may import', function () {
    it('lets the ceo and the operator import, refuses the team and a guest', function () {
        test()->post('/api/fiscal/imports', ['file' => nfeImportFile(NfeFixture::nfe())], ['Accept' => 'application/json'])->assertUnauthorized();

        nfeImportUpload(nfeImportPerson('operador'), nfeImportFile(NfeFixture::nfe()))->assertCreated();
        nfeImportUpload(nfeImportPerson('equipe'), nfeImportFile(NfeFixture::nfe()))->assertForbidden();
        nfeImportUpload(nfeImportPerson(PartnerAccessService::PARTNER_ROLE_SLUG), nfeImportFile(NfeFixture::nfe()))->assertForbidden();
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

        $response = nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe()))->assertCreated();
        $path = 'nfe/'.nfeImportFirmId().'/'.$seller->id.'/'.NfeFixture::key(NFE_SELLER_CNPJ, 123).'.xml';

        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => str_contains($uri, 'nfe') || str_contains($uri, 'fiscal'))->values()->all();

        expect(Storage::disk('local')->exists($path))->toBeTrue()
            ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
            ->and($response->getContent())->not->toContain('nfe/')
            ->and($routes)->toBe(['api/fiscal/imports'])
            ->and(test()->get('/storage/'.$path)->status())->not->toBe(200)
            ->and(test()->actingAs(nfeImportPerson('ceo'))->get('/storage/'.$path)->status())->not->toBe(200)
            ->and(test()->get('/storage/app/private/'.$path)->status())->not->toBe(200);
    });

    it('matches only the importing firm\'s clients, and each firm sees only its documents', function () {
        $otherFirm = Firm::factory()->create();
        nfeImportClient(NFE_SELLER_CNPJ, $otherFirm->id);
        $otherPerson = nfeImportPerson('ceo', $otherFirm->id);

        nfeImportUpload($otherPerson, nfeImportFile(NfeFixture::nfe()))->assertCreated()->assertJsonPath('data.stored', 1);
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe()))
            ->assertCreated()
            ->assertJsonPath('data.refused', [['file' => 'nota.xml', 'code' => 'client_unknown']]);

        $ours = nfeImportClient(NFE_SELLER_CNPJ);
        nfeImportUpload(nfeImportPerson('ceo'), nfeImportFile(NfeFixture::nfe()))->assertCreated()->assertJsonPath('data.stored', 1);

        $franco = Firm::where('slug', 'franco')->firstOrFail();
        $currentFirm = app(CurrentFirm::class);

        expect($currentFirm->runAs($franco, fn () => NfeDocument::query()->pluck('client_id')->all()))->toBe([$ours->id])
            ->and($currentFirm->runAs($otherFirm, fn () => NfeDocument::query()->count()))->toBe(1)
            ->and($currentFirm->runAs($franco, fn () => NfeImport::query()->count()))->toBe(2)
            ->and(NfeDocument::withoutGlobalScopes()->distinct()->pluck('access_key')->all())->toHaveCount(1);
    });
});
