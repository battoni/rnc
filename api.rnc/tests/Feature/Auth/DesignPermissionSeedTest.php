<?php

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Database\Seeders\ManagerSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * `design.read` replaced a front-end check on the administrator's role slug. Moving a gate from a
 * role to a permission is only safe if the seeded roles end up exactly where the role check left
 * them: the administrator can open the design surface, and nobody else can until the owner grants
 * it. `ManagerSeeder` hands the manager every key of every group, so without an exclusion the move
 * would quietly have given it these pages.
 */

function seededKeysFor(string $slug): array
{
    $role = Role::where('slug', $slug)->firstOrFail();

    return RolePermission::where('role_id', $role->id)->where('value', true)->pluck('permission_key')->all();
}

it('declares the design group with a single read key', function () {
    $design = collect(PermissionGroupsHelper::getPermissionGroupsStructure())->firstWhere('id', 'design');

    expect($design)->not->toBeNull()
        ->and(collect($design['permissions'])->pluck('key')->all())->toBe(['design.read']);
});

it('gives design.read to the administrator and to no other seeded role', function () {
    $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

    expect(seededKeysFor('administrator'))->toContain('design.read')
        ->and(seededKeysFor('manager'))->not->toContain('design.read')
        ->and(seededKeysFor('user'))->not->toContain('design.read');
});

it('still gives the manager every other key', function () {
    $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

    $allKeys = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
        ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('key'))
        ->reject(fn (string $key) => $key === 'design.read')
        ->values()
        ->all();

    expect(seededKeysFor('manager'))->toEqualCanonicalizing($allKeys);
});
