<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * A per-user row means "this person deliberately differs from their profile", and nothing else.
 *
 * The user form sends the whole matrix, and storing all of it made a copy of the profile frozen on
 * the day the account was made — so editing a profile reached nobody. lifeshifter found it when a
 * profile edit failed to take four unreleased screens away from somebody.
 */

function divergenceRoleWith(array $granted): Role
{
    $role = Role::create(['name' => 'Profile '.uniqid()]);

    foreach ($granted as $key) {
        RolePermission::create(['role_id' => $role->id, 'permission_key' => $key, 'value' => true]);
    }

    return $role;
}

function divergencePayload(Role $role, array $permissions): array
{
    return [
        'name' => 'Ana',
        'last_name' => 'Souza',
        'username' => 'ana'.uniqid(),
        'password' => 'a-long-enough-secret',
        'role_id' => $role->id,
        'status' => 'active',
        'permissions' => $permissions,
    ];
}

describe('what a user permission row is for', function () {
    it('stores nothing when the answer agrees with the profile', function () {
        $role = divergenceRoleWith(['tips.read', 'ideas.read']);

        $response = $this->actingAs(userWithPermissions(['users.create', 'tips.read', 'ideas.read']))
            ->postJson('/api/auth/users', divergencePayload($role, ['tips.read' => true, 'ideas.read' => true, 'users.read' => false]))
            ->assertCreated();

        expect(UserPermission::where('user_id', $response->json('data.id'))->count())->toBe(0);
    });

    it('keeps a grant the profile does not give', function () {
        $role = divergenceRoleWith(['tips.read']);

        $response = $this->actingAs(userWithPermissions(['users.create', 'tips.read', 'ideas.read']))
            ->postJson('/api/auth/users', divergencePayload($role, ['tips.read' => true, 'ideas.read' => true]));

        $rows = UserPermission::where('user_id', $response->json('data.id'))->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->permission_key)->toBe('ideas.read')
            ->and((bool) $rows->first()->value)->toBeTrue();
    });

    it('keeps a denial of something the profile grants', function () {
        $role = divergenceRoleWith(['tips.read', 'ideas.read']);

        $response = $this->actingAs(userWithPermissions(['users.create', 'tips.read', 'ideas.read']))
            ->postJson('/api/auth/users', divergencePayload($role, ['tips.read' => true, 'ideas.read' => false]));

        $rows = UserPermission::where('user_id', $response->json('data.id'))->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->permission_key)->toBe('ideas.read')
            ->and((bool) $rows->first()->value)->toBeFalse();
    });

    // The point of the change, as a test: take a key away from the profile and it goes away for the
    // people who never disagreed with it.
    it('lets a profile edit reach someone who never disagreed with it', function () {
        $role = divergenceRoleWith(['tips.read', 'ideas.read']);

        $response = $this->actingAs(userWithPermissions(['users.create', 'tips.read', 'ideas.read']))
            ->postJson('/api/auth/users', divergencePayload($role, ['tips.read' => true, 'ideas.read' => true]));

        $user = User::find($response->json('data.id'));
        expect(app(PermissionService::class)->forUser($user))->toContain('ideas.read');

        RolePermission::where('role_id', $role->id)->where('permission_key', 'ideas.read')->delete();
        app(PermissionService::class)->forgetUserMemo($user->id);

        expect(app(PermissionService::class)->forUser($user->fresh()))->not->toContain('ideas.read');
    });
});

describe('pruning the copies that already exist', function () {
    it('removes what agrees with the profile and keeps what does not', function () {
        $role = divergenceRoleWith(['tips.read', 'ideas.read']);
        $user = User::factory()->create(['role_id' => $role->id]);

        UserPermission::insert([
            ['user_id' => $user->id, 'permission_key' => 'tips.read', 'value' => true],
            ['user_id' => $user->id, 'permission_key' => 'users.read', 'value' => false],
            ['user_id' => $user->id, 'permission_key' => 'ideas.read', 'value' => false],
        ]);

        $this->artisan('permissions:prune-redundant')->assertSuccessful();

        $rows = UserPermission::where('user_id', $user->id)->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->permission_key)->toBe('ideas.read');
    });

    it('changes nothing on a dry run', function () {
        $role = divergenceRoleWith(['tips.read']);
        $user = User::factory()->create(['role_id' => $role->id]);

        UserPermission::create(['user_id' => $user->id, 'permission_key' => 'tips.read', 'value' => true]);

        $this->artisan('permissions:prune-redundant', ['--dry-run' => true])->assertSuccessful();

        expect(UserPermission::where('user_id', $user->id)->count())->toBe(1);
    });
});
