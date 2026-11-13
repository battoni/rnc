<?php

use App\Modules\Assistant\Contracts\ReformaAnswerer;
use App\Modules\Assistant\Drivers\CuratedNotesAnswerer;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/*
 * A15 (rnc-3 R16, D9, W9): the assistant answers through the `ReformaAnswerer` contract, bound by
 * `config('assistant.driver')` to a map whose only entry is `curated` — Franco's reviewed notes. An
 * unknown driver fails at boot, never falls back to one; and nothing under `app/Modules/Assistant`
 * can reach the network: no HTTP client, no Guzzle, no curl, no socket.
 *
 * AM22 red step (logged in state.md): a dummy driver calling `Http::get` under the module turned
 * the scan red, and was removed.
 */
describe('the driver (A15)', function () {
    it('binds the curated notes driver by default', function () {
        expect(config('assistant.driver'))->toBe('curated')
            ->and(app(ReformaAnswerer::class))->toBeInstanceOf(CuratedNotesAnswerer::class);
    });

    it('fails at boot on an unknown driver', function () {
        config(['assistant.driver' => 'modelo-de-linguagem']);

        expect(fn () => (new AppServiceProvider(app()))->boot())->toThrow(InvalidArgumentException::class, 'Unknown assistant driver [modelo-de-linguagem].');
    });

    it('refuses to resolve an unknown driver instead of falling back', function () {
        config(['assistant.driver' => 'modelo-de-linguagem']);
        app()->forgetInstance(ReformaAnswerer::class);

        expect(fn () => app(ReformaAnswerer::class))->toThrow(InvalidArgumentException::class, 'Unknown assistant driver [modelo-de-linguagem].');
    });

    it('has no outward client in any PHP file of the module: no Http::, Guzzle, curl_, fsockopen or socket', function () {
        $files = array_values(array_filter(File::allFiles(app_path('Modules/Assistant')), fn (SplFileInfo $file) => $file->getExtension() === 'php'));
        $forbidden = '/Http::|Guzzle|curl_|fsockopen|stream_socket_client|socket_create|Illuminate\\\\Http\\\\Client|file_get_contents\s*\(\s*[\'"]https?:/i';
        $offenders = collect($files)
            ->filter(fn (SplFileInfo $file) => preg_match($forbidden, (string) file_get_contents($file->getPathname())) === 1)
            ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
            ->values()
            ->all();

        expect(count($files))->toBeGreaterThan(10)
            ->and($offenders)->toBe([]);
    });
});
