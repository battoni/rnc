<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Lead\Models\Lead;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/*
 * A4, the Pest half (rnc-3 R4; plan AM13): the board filters by brand slug — `brand=express` only
 * Express's leads, `brand=main` only the main brand's, an unknown slug 422 — and the card and the
 * lead detail carry `brand {id, short_name}` (null for the main brand). The SPA half is F2's.
 */
beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->express = FirmBrand::withoutGlobalScopes()->where('slug', 'express')->firstOrFail();
    $this->prime = FirmBrand::withoutGlobalScopes()->where('slug', 'prime')->firstOrFail();
    $this->team = User::factory()->create(['firm_id' => $this->franco->id, 'role_id' => Role::where('slug', 'equipe')->value('id')]);

    $this->mainLead = Lead::factory()->create(['firm_id' => $this->franco->id, 'company' => 'Principal Ltda']);
    $this->expressLead = Lead::factory()->create(['firm_id' => $this->franco->id, 'brand_id' => $this->express->id, 'company' => 'Express Ltda']);
    $this->primeLead = Lead::factory()->create(['firm_id' => $this->franco->id, 'brand_id' => $this->prime->id, 'company' => 'Prime Ltda']);
});

/** @return list<string> every company on the board answer */
function boardCompanies(array $data): array
{
    return collect($data['columns'])->flatMap(fn (array $column) => array_column($column['items'], 'company'))->sort()->values()->all();
}

/** @return int the sum of the column counts */
function boardTotal(array $data): int
{
    return array_sum(array_column($data['columns'], 'count'));
}

describe('GET /api/leads?brand= (A4)', function () {
    it('answers only the leads of the brand named', function () {
        $data = $this->actingAs($this->team)->getJson('/api/leads?brand=express')->assertOk()->json('data');

        expect(boardCompanies($data))->toBe(['Express Ltda'])->and(boardTotal($data))->toBe(1);
    });

    it('answers only the main brand for brand=main', function () {
        $data = $this->actingAs($this->team)->getJson('/api/leads?brand=main')->assertOk()->json('data');

        expect(boardCompanies($data))->toBe(['Principal Ltda'])->and(boardTotal($data))->toBe(1);
    });

    it('answers every brand without the filter', function () {
        $data = $this->actingAs($this->team)->getJson('/api/leads')->assertOk()->json('data');

        expect(boardCompanies($data))->toBe(['Express Ltda', 'Prime Ltda', 'Principal Ltda']);
    });

    it('still filters by a brand that was deactivated', function () {
        $this->prime->update(['active' => false]);

        expect(boardCompanies($this->actingAs($this->team)->getJson('/api/leads?brand=prime')->assertOk()->json('data')))->toBe(['Prime Ltda']);
    });

    it("refuses an unknown slug, or another firm's, with 422", function (string $slug) {
        FirmBrand::factory()->create(['firm_id' => Firm::factory()->create()->id, 'slug' => 'alheia']);

        $this->actingAs($this->team)->getJson("/api/leads?brand={$slug}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand' => 'brands.notFound']);
    })->with(['nada', 'alheia']);
});

describe('the brand chip (A4)', function () {
    it('is on the card: {id, short_name}, null for the main brand', function () {
        $cards = collect($this->actingAs($this->team)->getJson('/api/leads')->json('data.columns'))->flatMap(fn (array $column) => $column['items'])->keyBy('company');

        expect($cards['Express Ltda']['brand'])->toBe(['id' => $this->express->id, 'short_name' => 'Franco Express'])
            ->and($cards['Prime Ltda']['brand'])->toBe(['id' => $this->prime->id, 'short_name' => 'Franco Prime'])
            ->and($cards['Principal Ltda']['brand'])->toBeNull();
    });

    it('is on the lead detail', function () {
        $this->actingAs($this->team)->getJson("/api/leads/{$this->primeLead->id}")
            ->assertOk()
            ->assertJsonPath('data.brand', ['id' => $this->prime->id, 'short_name' => 'Franco Prime']);

        $this->actingAs($this->team)->getJson("/api/leads/{$this->mainLead->id}")->assertOk()->assertJsonPath('data.brand', null);
    });

    it('loads the brands of a board once, not once per card', function () {
        $queriesFor = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->team)->getJson('/api/leads')->assertOk();

            return count(DB::getQueryLog());
        };

        $before = $queriesFor();
        Lead::factory()->count(5)->create(['firm_id' => $this->franco->id, 'brand_id' => $this->express->id]);

        expect($queriesFor())->toBe($before);
    });
});

describe("the board's brand options (rnc-3 F2, R4)", function () {
    it("lists the firm's sub-brands for the filter, inactive too, never another firm's", function () {
        FirmBrand::factory()->create(['firm_id' => Firm::factory()->create()->id, 'slug' => 'alheia', 'name' => 'Alheia']);
        $this->prime->update(['active' => false]);

        $this->actingAs($this->team)->getJson('/api/leads')
            ->assertOk()
            ->assertJsonPath('data.brands', [
                ['slug' => 'express', 'short_name' => 'Franco Express', 'active' => true],
                ['slug' => 'prime', 'short_name' => 'Franco Prime', 'active' => false],
            ]);
    });
});
