<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Repositories\BrandRepository;
use App\Modules\Firm\Scopes\FirmScope;
use App\Modules\Firm\Services\CurrentFirm;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FirmSeeder;
use Illuminate\Database\Eloquent\Model;

/*
 * rnc-3 branch review A1: `X-Brand` is a visitor's header, so the public brand lookup names the
 * current firm itself instead of trusting `BelongsToFirm`'s global scope alone. With the scope taken
 * off `FirmBrand`, another firm's slug is still unknown, and no current firm finds nothing.
 */
beforeEach(function () {
    $this->seed([FirmSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->firmB = Firm::factory()->create(['slug' => 'firma-b']);
    FirmBrand::withoutGlobalScopes()->forceCreate([
        'firm_id' => $this->firmB->id, 'slug' => 'so-da-b', 'name' => 'Marca da B', 'short_name' => 'B',
        'brand_primary' => '#3b2a86', 'brand_accent' => '#ff8a3d', 'proof' => '[]', 'active' => true,
    ]);

    // The global scope taken off `FirmBrand`, as a refactor or a `withoutGlobalScopes()` would.
    $scopes = (new ReflectionProperty(Model::class, 'globalScopes'))->getValue();
    unset($scopes[FirmBrand::class][FirmScope::class]);
    (new ReflectionProperty(Model::class, 'globalScopes'))->setValue(null, $scopes);
});

afterEach(fn () => FirmBrand::clearBootedModels());

it("finds no other firm's brand by slug even without the global scope", function () {
    app(CurrentFirm::class)->set($this->franco);

    expect(app(BrandRepository::class)->findActiveBrandBySlug('so-da-b'))->toBeNull()
        ->and(app(BrandRepository::class)->findActiveBrandBySlug('express')?->firm_id)->toBe($this->franco->id);
});

it('finds nothing when no firm is current', function () {
    app(CurrentFirm::class)->set(null);

    expect(app(BrandRepository::class)->findActiveBrandBySlug('express'))->toBeNull();
});
