<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Portfolio\Models\Client;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\NfeXmlBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\NfeFixture;

/*
 * The folder import (rnc-2 F6b, R15, A14's command half; plan AM3):
 *
 *   php artisan nfe:import <folder> --firm=<slug>
 *
 * The same `NfeImporter` as the upload, batch source `folder`, no user. The folder is resolved with
 * `realpath` and must be a directory; only its own `.xml` files are read (no subfolder), each at most
 * 1 MB + 1 byte, and a symbolic link — the folder itself or an entry — is never followed.
 */
const NFE_FOLDER_SELLER_CNPJ = '11222333000181';

const NFE_FOLDER_STRANGER_CNPJ = '33444555000181';

function nfeFolderFirmId(): int
{
    return (int) Firm::where('slug', 'franco')->value('id');
}

function nfeFolderClient(string $cnpj): Client
{
    return Client::withoutGlobalScopes()->create([
        'firm_id' => nfeFolderFirmId(),
        'name' => "Cliente {$cnpj} Ltda",
        'cnpj' => $cnpj,
        'sector' => 'comercio',
        'regime' => 'presumido',
        'band' => 'b3',
        'b2b_share' => 70,
        'monthly_fee' => 3000,
    ]);
}

/**
 * The temp paths a test made, removed after it: `nfeFolderTrack($path)` adds one, `nfeFolderTrack()`
 * hands the list over and empties it.
 *
 * @return list<string>
 */
function nfeFolderTrack(?string $path = null): array
{
    static $paths = [];

    if ($path !== null) {
        $paths[] = $path;

        return $paths;
    }

    [$tracked, $paths] = [$paths, []];

    return $tracked;
}

/** A fresh, empty temp folder, removed after the test. */
function nfeFolderMake(): string
{
    $folder = sys_get_temp_dir().'/nfe-folder-'.bin2hex(random_bytes(6));
    mkdir($folder);
    nfeFolderTrack($folder);

    return $folder;
}

/** @param  array<string, string>  $files  a second folder, for a first import before the one a test checks */
function nfeFolderMakeWith(array $files): string
{
    $folder = nfeFolderMake();

    foreach ($files as $name => $content) {
        file_put_contents($folder.'/'.$name, $content);
    }

    return $folder;
}

/** A symbolic link next to `$folder`, pointing at it, removed after the test. */
function nfeFolderLinkTo(string $folder): string
{
    $link = $folder.'-atalho';
    symlink($folder, $link);
    nfeFolderTrack($link);

    return $link;
}

function nfeFolderRemove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
        nfeFolderRemove($path.'/'.$entry);
    }

    rmdir($path);
}

/** @return array{0: int, 1: string} the exit code and the output */
function nfeFolderRun(string $folder, ?string $firm = 'franco'): array
{
    $arguments = ['folder' => $folder];

    if ($firm !== null) {
        $arguments['--firm'] = $firm;
    }

    $code = Artisan::call('nfe:import', $arguments);

    return [$code, Artisan::output()];
}

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
});

afterEach(function () {
    foreach (nfeFolderTrack() as $folder) {
        nfeFolderRemove($folder);
    }
});

describe('a folder of XMLs (A14)', function () {
    it('imports three builder files as 1 stored, 1 duplicate and 1 client_unknown', function () {
        nfeFolderClient(NFE_FOLDER_SELLER_CNPJ);
        $folder = nfeFolderMake();
        file_put_contents($folder.'/a-nova.xml', NfeXmlBuilder::nfe(['number' => 10]));
        file_put_contents($folder.'/b-repetida.xml', NfeXmlBuilder::nfe(['number' => 20]));
        file_put_contents($folder.'/c-estranha.xml', NfeXmlBuilder::nfe(['emitter' => NFE_FOLDER_STRANGER_CNPJ, 'recipient' => '44555666000181']));
        nfeFolderRun(nfeFolderMakeWith(['b-repetida.xml' => NfeXmlBuilder::nfe(['number' => 20])]));

        [$code, $output] = nfeFolderRun($folder);

        $batch = NfeImport::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();

        expect($code)->toBe(0)
            ->and($batch->only(['source', 'user_id', 'files', 'stored', 'refused']))->toBeIgnoringKeyOrder([
                'source' => 'folder',
                'user_id' => null,
                'files' => 3,
                'stored' => 1,
                'refused' => [
                    ['file' => 'b-repetida.xml', 'code' => 'duplicate'],
                    ['file' => 'c-estranha.xml', 'code' => 'client_unknown'],
                ],
            ])
            ->and(NfeDocument::withoutGlobalScopes()->count())->toBe(2)
            ->and($output)->toContain('3 arquivo(s) lido(s)')
            ->and($output)->toContain('1 nota(s) guardada(s)')
            ->and($output)->toContain('c-estranha.xml')
            ->and($output)->toContain('client_unknown');
    });

    it('reads the folder through ../ the same as its real path', function () {
        nfeFolderClient(NFE_FOLDER_SELLER_CNPJ);
        $folder = nfeFolderMake();
        file_put_contents($folder.'/nota.xml', NfeXmlBuilder::nfe());

        [$code] = nfeFolderRun($folder.'/../'.basename($folder));

        expect($code)->toBe(0)
            ->and(NfeDocument::withoutGlobalScopes()->count())->toBe(1);
    });

    it('reads only the folder\'s own .xml files: no subfolder, no other name', function () {
        nfeFolderClient(NFE_FOLDER_SELLER_CNPJ);
        $folder = nfeFolderMake();
        file_put_contents($folder.'/nota.XML', NfeXmlBuilder::nfe());
        file_put_contents($folder.'/leia-me.txt', NfeXmlBuilder::nfe(['number' => 2]));
        file_put_contents($folder.'/lote.zip', NfeFixture::zip(['dentro.xml' => NfeXmlBuilder::nfe(['number' => 3])]));
        mkdir($folder.'/sub.xml');
        file_put_contents($folder.'/sub.xml/funda.xml', NfeXmlBuilder::nfe(['number' => 4]));

        nfeFolderRun($folder);

        expect(NfeImport::withoutGlobalScopes()->firstOrFail()->only(['files', 'stored', 'refused']))->toBe(['files' => 1, 'stored' => 1, 'refused' => []])
            ->and(NfeDocument::withoutGlobalScopes()->pluck('number')->all())->toBe([123]);
    });

    it('never follows a symbolic link inside the folder', function () {
        nfeFolderClient(NFE_FOLDER_SELLER_CNPJ);
        $outside = nfeFolderMake();
        file_put_contents($outside.'/fora.xml', NfeXmlBuilder::nfe(['number' => 99]));
        $folder = nfeFolderMake();
        file_put_contents($folder.'/nota.xml', NfeXmlBuilder::nfe());
        symlink($outside.'/fora.xml', $folder.'/atalho.xml');
        symlink($outside, $folder.'/pasta.xml');

        nfeFolderRun($folder);

        expect(NfeImport::withoutGlobalScopes()->firstOrFail()->only(['files', 'stored']))->toBe(['files' => 1, 'stored' => 1])
            ->and(NfeDocument::withoutGlobalScopes()->pluck('number')->all())->toBe([123]);
    });

    it('refuses a file over 1 MB as too_large', function () {
        nfeFolderClient(NFE_FOLDER_SELLER_CNPJ);
        $folder = nfeFolderMake();
        file_put_contents($folder.'/grande.xml', str_replace('</nfeProc>', str_repeat(' ', 1_048_576).'</nfeProc>', NfeXmlBuilder::nfe()));

        nfeFolderRun($folder);

        expect(NfeImport::withoutGlobalScopes()->firstOrFail()->refused)->toBeIgnoringKeyOrder([['file' => 'grande.xml', 'code' => 'too_large']])
            ->and(NfeDocument::withoutGlobalScopes()->count())->toBe(0);
    });

    it('reads no more than 1 MB of a large file', function () {
        $folder = nfeFolderMake();
        $handle = fopen($folder.'/enorme.xml', 'wb');

        for ($chunk = 0; $chunk < 32; $chunk++) {
            fwrite($handle, str_repeat(' ', 1_048_576));
        }

        fclose($handle);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        nfeFolderRun($folder);
        $peak = memory_get_peak_usage() - $before;

        expect(NfeImport::withoutGlobalScopes()->firstOrFail()->refused)->toBeIgnoringKeyOrder([['file' => 'enorme.xml', 'code' => 'too_large']])
            ->and($peak)->toBeLessThan(16 * 1_048_576);
    });
});

describe('what the command refuses to start on (plan AM3)', function () {
    it('refuses a missing folder, a file, a symbolic link to a folder and an unknown or absent firm', function (string $case, ?string $firm, string $message) {
        $folder = nfeFolderMake();
        file_put_contents($folder.'/nota.xml', NfeXmlBuilder::nfe());

        $argument = match ($case) {
            'missing' => $folder.'/nao-existe',
            'file' => $folder.'/nota.xml',
            'link' => nfeFolderLinkTo($folder),
            default => $folder,
        };

        [$code, $output] = nfeFolderRun($argument, $firm);

        expect($code)->toBe(1)
            ->and($output)->toContain($message)
            ->and(NfeImport::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'a missing folder' => ['missing', 'franco', 'não é uma pasta'],
        'a file' => ['file', 'franco', 'não é uma pasta'],
        'a symbolic link to the folder' => ['link', 'franco', 'link simbólico'],
        'an unknown firm' => ['folder', 'outra', 'Firma desconhecida'],
        'no firm' => ['folder', null, 'Firma desconhecida'],
    ]);
});
