<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A21 (rnc-4 R20): `GET auth/me` answers the person's firm — slug, name, its opt-in modules and a
 * brand summary — or null for a firm-less account, plus `platform`: the product's name, whether
 * the person is the platform operator, and the firm being viewed as (null until F4c).
 */
beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]));

it('answers the firm with its modules and brand, and the platform, for a firm’s manager', function () {
    $franco = Firm::where('slug', 'franco')->firstOrFail();
    $franco->forceFill(['modules' => ['partners', 'briefing']])->save();

    $me = $this->actingAs(User::where('username', 'reinaldo')->firstOrFail())->getJson('/api/auth/me')->assertOk()->json('data');

    expect($me['firm'])->toBe([
        'slug' => 'franco',
        'name' => 'Franco Contabilidade',
        'short_name' => 'Franco',
        'logo_text' => 'Franco Contabilidade',
        'logo_url' => null,
        'brand_primary' => '#00375a',
        'brand_accent' => '#14c3ff',
        'is_platform' => false,
        'modules' => ['partners', 'briefing'],
    ])
        ->and($me['platform'])->toBe(['name' => (string) config('platform.name'), 'is_operator' => false, 'view_as' => null]);
});

it('answers firm null and is_operator true for the firm-less operator (A21)', function () {
    config(['firms.default_slug' => 'hub']);
    Firm::factory()->create(['slug' => 'hub', 'name' => 'Hub da Reforma', 'is_platform' => true]);
    $operator = User::factory()->create(['firm_id' => null, 'role_id' => Role::where('slug', 'operador')->value('id')]);

    $me = $this->actingAs($operator)->getJson('/api/auth/me')->assertOk()->json('data');

    expect($me['firm'])->toBeNull()
        ->and($me['platform'])->toBe(['name' => (string) config('platform.name'), 'is_operator' => true, 'view_as' => null]);
});

it('answers only the modules the catalogue still knows', function () {
    Firm::where('slug', 'franco')->firstOrFail()->forceFill(['modules' => ['briefing', 'retired-module']])->save();

    $me = $this->actingAs(User::where('username', 'reinaldo')->firstOrFail())->getJson('/api/auth/me')->assertOk()->json('data');

    expect($me['firm']['modules'])->toBe(['briefing']);
});
