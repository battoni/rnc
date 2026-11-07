<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The permissions screen must be able to grant a permission that was just declared, and must not be
 * able to erase one it never asked about. In lifeshifter both failed in production: a new group was
 * invisible because no role had a row for its key, and an edit naming fewer groups than the role
 * held emptied the rest with a 200. Here the first would have hidden `design.read` from every role
 * but the administrator's.
 */

function matrixEditor(): User
{
    $user = User::factory()->create();

    UserPermission::create(['user_id' => $user->id, 'permission_key' => 'permissions.read', 'value' => true]);
    UserPermission::create(['user_id' => $user->id, 'permission_key' => 'permissions.update', 'value' => true]);
    // Plus the keys these specs write into a matrix: a grant is capped by the giver's own (ARC-03).
    UserPermission::create(['user_id' => $user->id, 'permission_key' => 'leads.read', 'value' => true]);

    return $user;
}

describe('the permission matrix a role is shown', function () {
    it('carries every declared group, granted or not', function () {
        $role = Role::create(['name' => 'Fresh Role']);

        $groupIds = collect($this->actingAs(matrixEditor())
            ->getJson("/api/auth/roles/{$role->id}")
            ->assertOk()
            ->json('data.permissionGroups'))->pluck('id');

        // The role has no rows at all. That used to mean an EMPTY matrix, and a group missing here
        // could never come back, because the screen saves only what it rendered.
        expect($groupIds)->toContain('users')
            ->and($groupIds)->toContain('leads')
            ->and($groupIds)->toContain('referrals');
    });

    it('reports an ungranted key as false rather than hiding its group', function () {
        $role = Role::create(['name' => 'Fresh Role']);

        $design = collect($this->actingAs(matrixEditor())
            ->getJson("/api/auth/roles/{$role->id}")
            ->json('data.permissionGroups'))->firstWhere('id', 'referrals');

        expect($design['permissions'])->not->toBeEmpty()
            ->and($design['permissions'][0]['value'])->toBeFalse();
    });
});

describe('which groups a role holds', function () {
    // Every declared group comes back now, so the edit form cannot read "held" from presence any
    // more: it pre-selected all of them, and a group removed in the form came back on the next open.
    it('marks the groups the role has rows for, and only those', function () {
        $role = Role::create(['name' => 'Held Role']);

        RolePermission::insert([
            ['role_id' => $role->id, 'permission_key' => 'leads.read', 'value' => true],
            ['role_id' => $role->id, 'permission_key' => 'leads.update', 'value' => false],
        ]);

        $groups = collect($this->actingAs(matrixEditor())
            ->getJson("/api/auth/roles/{$role->id}")
            ->assertOk()
            ->json('data.permissionGroups'))->keyBy('id');

        expect($groups['leads']['isHeld'])->toBeTrue()
            ->and($groups['users']['isHeld'])->toBeFalse()
            ->and($groups['referrals']['isHeld'])->toBeFalse();
    });
});

describe('editing which groups a role holds', function () {
    it('revokes only the keys of the groups it dropped', function () {
        $role = Role::create(['name' => 'Trimmed Role']);

        RolePermission::insert([
            ['role_id' => $role->id, 'permission_key' => 'leads.read', 'value' => true],
            ['role_id' => $role->id, 'permission_key' => 'leads.update', 'value' => true],
            ['role_id' => $role->id, 'permission_key' => 'agenda.read', 'value' => true],
        ]);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}", ['permission_group_ids' => ['leads']])
            ->assertOk();

        $keys = RolePermission::where('role_id', $role->id)->pluck('value', 'permission_key');

        expect($keys->get('leads.read'))->toBeTrue()
            ->and($keys->get('leads.update'))->toBeTrue()
            ->and($keys->has('agenda.read'))->toBeFalse();
    });

    it('does not empty the matrix when the payload names a group the role lacks', function () {
        $role = Role::create(['name' => 'Growing Role']);

        RolePermission::insert([
            ['role_id' => $role->id, 'permission_key' => 'leads.read', 'value' => true],
        ]);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}", ['permission_group_ids' => ['leads', 'metrics']])
            ->assertOk();

        $keys = RolePermission::where('role_id', $role->id)->pluck('value', 'permission_key');

        expect($keys->get('leads.read'))->toBeTrue()
            ->and($keys->has('metrics.read'))->toBeTrue()
            ->and($keys->get('metrics.read'))->toBeFalse();
    });

    it('refuses an unknown group id instead of silently dropping it', function () {
        $role = Role::create(['name' => 'Typo Role']);

        RolePermission::insert([
            ['role_id' => $role->id, 'permission_key' => 'leads.read', 'value' => true],
        ]);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}", ['permission_group_ids' => ['leads', 'laeds']])
            ->assertStatus(422);

        expect(RolePermission::where('role_id', $role->id)->count())->toBe(1);
    });

    it('refuses an unknown group id when a role is created too', function () {
        $this->actingAs(matrixEditor())
            ->postJson('/api/auth/roles', ['name' => 'Typo Role', 'permission_group_ids' => ['laeds']])
            ->assertStatus(422);

        expect(Role::where('name', 'Typo Role')->exists())->toBeFalse();
    });
});

describe('editing the matrix itself', function () {
    // PATCH promises a delta. Replacing the whole matrix meant a payload carrying one key erased
    // every other permission the role had, with a 200.
    it('leaves permissions the request did not mention alone', function () {
        $role = Role::create(['name' => 'Editor']);

        RolePermission::create(['role_id' => $role->id, 'permission_key' => 'users.read', 'value' => true]);
        RolePermission::create(['role_id' => $role->id, 'permission_key' => 'users.create', 'value' => true]);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['users.create' => false]])
            ->assertOk();

        $permissions = RolePermission::where('role_id', $role->id)->pluck('value', 'permission_key');

        expect($permissions['users.read'])->toBeTrue()
            ->and($permissions['users.create'])->toBeFalse();
    });

    // Absence is not denial: a key set to false is a recorded no, and it has to survive.
    it('records a denial rather than dropping the key', function () {
        $role = Role::create(['name' => 'Editor']);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['users.read' => false]])
            ->assertOk();

        expect(RolePermission::where('role_id', $role->id)->where('permission_key', 'users.read')->exists())->toBeTrue();
    });
});

describe('who may read the structure of permission groups', function () {
    it('opens to the Roles screen and to the user form audience', function (string $key) {
        $this->actingAs(userWithPermissions([$key]))
            ->getJson('/api/auth/permission-groups')
            ->assertOk();
    })->with(['permissions.read', 'users.create', 'users.update']);

    it('refuses a signed-in account with none of the keys', function () {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/auth/permission-groups')
            ->assertForbidden();
    });
});

describe('setting keys on the matrix', function () {
    // The request checked that each value was a boolean and never asked whether the KEY exists, so
    // anyone holding `permissions.update` could store a key no screen declares — granted in advance
    // to whatever route adopts that name later.
    it('refuses a permission key that no group declares', function () {
        $role = Role::create(['name' => 'Matrix Role']);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['billing.export' => true]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions');

        expect(RolePermission::where('role_id', $role->id)->where('permission_key', 'billing.export')->exists())->toBeFalse();
    });

    it('still sets a declared key', function () {
        $role = Role::create(['name' => 'Matrix Role']);

        $this->actingAs(matrixEditor())
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['leads.read' => true]])
            ->assertOk();

        expect(RolePermission::where('role_id', $role->id)->where('permission_key', 'leads.read')->value('value'))->toBeTruthy();
    });
});
