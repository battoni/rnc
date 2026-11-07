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
 * A5 (rnc-3 R5; D3, D4): the "Marcas" console's API, behind `brands.manage` (operador only).
 *
 *   GET   /api/brands                    Franco's row and the sub-brands, each with its lead count
 *   PATCH /api/brands/{id}               name, short name, tagline, colours, the four proof points
 *   POST  /api/brands/{id}/activate|deactivate
 *
 * White on the primary must reach 4,5:1 (`brand.lowContrast`); another firm's brand is a 404.
 */
beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->express = FirmBrand::withoutGlobalScopes()->where('slug', 'express')->firstOrFail();
    $this->prime = FirmBrand::withoutGlobalScopes()->where('slug', 'prime')->firstOrFail();
});

function brandAdminPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

/** @return array<string, mixed> a complete, valid edit */
function brandEdit(array $overrides = []): array
{
    return array_merge([
        'name' => 'Franco Express Contabilidade',
        'short_name' => 'Franco Express',
        'tagline' => 'A Franco no ritmo da pequena empresa',
        'brand_primary' => '#2d1f6e',
        'brand_accent' => '#ff8a3d',
        'proof' => [
            ['value' => '35 anos', 'label' => 'da Franco em São Paulo'],
            ['value' => 'MEI', 'label' => 'e Simples Nacional'],
            ['value' => 'Online', 'label' => 'rotina sem papel'],
            ['value' => 'RNC', 'label' => 'Rede Nacional de Contabilidade'],
        ],
    ], $overrides);
}

describe('GET /api/brands (A5)', function () {
    it('lists the main brand and the sub-brands with their lead counts', function () {
        Lead::factory()->count(3)->create(['firm_id' => $this->franco->id]);
        Lead::factory()->count(2)->create(['firm_id' => $this->franco->id, 'brand_id' => $this->express->id]);
        Lead::factory()->create(['firm_id' => $this->franco->id, 'brand_id' => $this->prime->id]);

        $data = $this->actingAs(brandAdminPerson('operador'))->getJson('/api/brands')->assertOk()->json('data');

        expect($data['main'])->toMatchArray(['name' => 'Franco Contabilidade', 'brand_primary' => '#00375a', 'lead_count' => 3])
            ->and(collect($data['brands'])->pluck('lead_count', 'slug')->all())->toBe(['express' => 2, 'prime' => 1])
            ->and($data['brands'][0])->toHaveKeys(['id', 'slug', 'name', 'short_name', 'tagline', 'brand_primary', 'brand_accent', 'proof', 'active', 'lead_count']);
    });

    it('counts the leads in one grouped query, whatever the number of brands', function () {
        $person = brandAdminPerson('operador');
        $queriesFor = function () use ($person): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($person)->getJson('/api/brands')->assertOk();

            return count(DB::getQueryLog());
        };

        $withTwo = $queriesFor();
        FirmBrand::factory()->count(4)->create(['firm_id' => $this->franco->id]);

        expect($queriesFor())->toBe($withTwo);
    });
});

describe('PATCH /api/brands/{id} (A5)', function () {
    it('updates a brand and answers it', function () {
        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit())
            ->assertOk()
            ->assertJsonPath('data.tagline', 'A Franco no ritmo da pequena empresa')
            ->assertJsonPath('data.brand_primary', '#2d1f6e')
            ->assertJsonPath('data.proof.1', ['value' => 'MEI', 'label' => 'e Simples Nacional']);

        expect($this->express->refresh()->brand_primary)->toBe('#2d1f6e');
    });

    it('refuses a colour that is not #rrggbb', function (string $colour) {
        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit(['brand_accent' => $colour]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_accent' => 'brands.invalidColour']);
    })->with(['#fff', 'ff8a3d', '#gg8a3d', 'red', '#ff8a3d0']);

    it('refuses a primary white text cannot be read on (brand.lowContrast)', function () {
        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit(['brand_primary' => '#ffff00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_primary' => 'brand.lowContrast']);

        expect($this->express->refresh()->brand_primary)->toBe('#3b2a86');
    });

    it('takes exactly four proof points within their lengths', function (array $proof) {
        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit(['proof' => $proof]))
            ->assertUnprocessable();
    })->with([
        'three' => [[['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b']]],
        'five' => [array_fill(0, 5, ['value' => 'a', 'label' => 'b'])],
        'a value over 20' => [[['value' => str_repeat('a', 21), 'label' => 'b'], ['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b']]],
        'a label over 60' => [[['value' => 'a', 'label' => str_repeat('b', 61)], ['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b'], ['value' => 'a', 'label' => 'b']]],
    ]);

    it('caps the name, short name and tagline', function (string $field, int $max) {
        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit([$field => str_repeat('a', $max + 1)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([['name', 120], ['short_name', 40], ['tagline', 160]]);

    it('never moves a brand into another firm or renames its slug', function () {
        $other = Firm::factory()->create();

        $this->actingAs(brandAdminPerson('operador'))->patchJson("/api/brands/{$this->express->id}", brandEdit(['firm_id' => $other->id, 'slug' => 'roubada']))
            ->assertOk();

        expect(FirmBrand::withoutGlobalScopes()->findOrFail($this->express->id))->firm_id->toBe($this->franco->id)->slug->toBe('express');
    });
});

describe('activate and deactivate (A5)', function () {
    it('deactivates and reactivates a brand', function () {
        $operator = brandAdminPerson('operador');

        $this->actingAs($operator)->postJson("/api/brands/{$this->prime->id}/deactivate")->assertOk()->assertJsonPath('data.active', false);
        expect($this->prime->refresh()->active)->toBeFalse();

        $this->actingAs($operator)->postJson("/api/brands/{$this->prime->id}/activate")->assertOk()->assertJsonPath('data.active', true);
        expect($this->prime->refresh()->active)->toBeTrue();
    });
});

describe('who may manage brands (A5)', function () {
    it('refuses ceo, equipe and parceiro with 403', function (string $role, string $method, string $uri) {
        $url = str_replace('{id}', (string) $this->express->id, $uri);

        $this->actingAs(brandAdminPerson($role))->json($method, $url, brandEdit())->assertForbidden();
    })->with(['ceo', 'equipe', 'parceiro'])->with([
        ['GET', '/api/brands'],
        ['PATCH', '/api/brands/{id}'],
        ['POST', '/api/brands/{id}/activate'],
        ['POST', '/api/brands/{id}/deactivate'],
    ]);

    it("answers 404 brands.notFound for another firm's brand", function (string $method, string $uri) {
        $theirs = FirmBrand::factory()->create(['firm_id' => Firm::factory()->create()->id, 'active' => true]);

        $this->actingAs(brandAdminPerson('operador'))->json($method, str_replace('{id}', (string) $theirs->id, $uri), brandEdit())
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');

        expect(FirmBrand::withoutGlobalScopes()->findOrFail($theirs->id))->active->toBeTrue()->name->not->toBe('Franco Express Contabilidade');
    })->with([
        ['PATCH', '/api/brands/{id}'],
        ['POST', '/api/brands/{id}/activate'],
        ['POST', '/api/brands/{id}/deactivate'],
    ]);
});
