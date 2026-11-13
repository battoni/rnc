<?php

use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Models\NfeImport;
use Database\Seeders\ClientSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\NfeSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * `NfeSeeder` (rnc-2 F6b, R29; plan AM7) builds 90 days of invented NF-e for 40 clients through
 * `NfeImporter` on `make fresh-seed`. The Pest suite never runs it — the tests build their own
 * documents — and production never does: the full seed here imports nothing and writes no file.
 * (Its own output is checked by `make fresh-seed`, its time recorded in the feature's state.md.)
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

it('stays out of the Pest suite: the full seed imports no NF-e and writes no file (AM7)', function () {
    $this->seed();

    expect(NfeImport::withoutGlobalScopes()->count())->toBe(0)
        ->and(NfeDocument::withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('is registered in the local seeders, after the portfolio it reads', function () {
    $local = (string) file_get_contents(database_path('seeders/seeders.local.php'));

    expect($local)->toContain('$this->call(NfeSeeder::class);')
        ->and(strpos($local, '$this->call(NfeSeeder::class);'))->toBeGreaterThan((int) strpos($local, '$this->call(ClientSeeder::class);'));
});

it('refuses production (R29), even with the portfolio in place', function () {
    $this->seed([FirmSeeder::class, ClientSeeder::class]);
    app()->detectEnvironment(fn () => 'production');

    app(NfeSeeder::class)->setContainer(app())->__invoke();

    expect(NfeImport::withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
