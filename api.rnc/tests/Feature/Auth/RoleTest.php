<?php

use App\Models\User;
use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Models\UserPermission;
use Database\Seeders\ManagerSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsWithRole(array $permissions = []): User
{
    $user = User::factory()->create();
    foreach ($permissions as $key) {
        UserPermission::create(['user_id' => $user->id, 'permission_key' => $key, 'value' => true]);
    }

    return $user;
}

function createRole(string $name = 'Test Role'): Role
{
    return Role::create(['name' => $name]);
}

// ---------------------------------------------------------------------------
// GET /api/auth/roles
// ---------------------------------------------------------------------------

describe('GET /api/auth/roles', function () {
    it('returns list of roles', function () {
        createRole('Admin');
        createRole('Support');

        $response = $this->actingAs(actingAsWithRole(['permissions.read']))->getJson('/api/auth/roles');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('opens to the user form audience too, without the Roles screen key', function (string $key) {
        $this->actingAs(actingAsWithRole([$key]))
            ->getJson('/api/auth/roles')
            ->assertOk();
    })->with(['users.create', 'users.update']);

    it('rejects a signed-in account with none of the keys', function () {
        $this->actingAs(actingAsWithRole())
            ->getJson('/api/auth/roles')
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function () {
        $this->getJson('/api/auth/roles')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// GET /api/auth/roles/{id}
// ---------------------------------------------------------------------------

describe('GET /api/auth/roles/{id}', function () {
    it('returns a single role', function () {
        $role = createRole('Admin');

        $response = $this->actingAs(actingAsWithRole(['permissions.read']))->getJson("/api/auth/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', 'Admin');
    });

    it('returns 404 for unknown role', function () {
        $response = $this->actingAs(actingAsWithRole(['permissions.read']))->getJson('/api/auth/roles/999999');

        $response->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// POST /api/auth/roles
// ---------------------------------------------------------------------------

describe('POST /api/auth/roles', function () {
    it('creates a role when authenticated', function () {
        $actor = actingAsWithRole(['permissions.update']);

        $response = $this->actingAs($actor)->postJson('/api/auth/roles', [
            'name' => 'New Role',
            'description' => 'A description',
            'permission_group_ids' => ['users'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Role');
    });

    it('rejects an account that can read roles but not change them', function () {
        $this->actingAs(actingAsWithRole(['permissions.read', 'users.create']))
            ->postJson('/api/auth/roles', ['name' => 'Escalated', 'permission_group_ids' => ['users']])
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function () {
        $response = $this->postJson('/api/auth/roles', [
            'name' => 'New Role',
            'permission_group_ids' => [],
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $actor = actingAsWithRole(['permissions.update']);

        $response = $this->actingAs($actor)->postJson('/api/auth/roles', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/auth/roles/{id}
// ---------------------------------------------------------------------------

describe('PATCH /api/auth/roles/{id}', function () {
    it('updates a role name when authenticated', function () {
        $actor = actingAsWithRole(['permissions.update']);
        $role = createRole('Old Name');

        $response = $this->actingAs($actor)->patchJson("/api/auth/roles/{$role->id}", [
            'name' => 'Updated Name',
            'description' => '',
            'permission_group_ids' => ['users'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    });

});

// ---------------------------------------------------------------------------
// PATCH /api/auth/roles/{id}/permissions
// ---------------------------------------------------------------------------

describe('PATCH /api/auth/roles/{id}/permissions', function () {
    it('updates role permissions when authenticated', function () {
        // `users.read` is on the actor as well as in the payload: since ARC-03 a person hands out
        // only keys they hold themselves, and this spec is about writing the matrix, not about
        // the ceiling on it.
        $actor = actingAsWithRole(['permissions.update', 'users.read']);
        $role = createRole('Admin');

        $response = $this->actingAs($actor)->patchJson("/api/auth/roles/{$role->id}/permissions", [
            'permissions' => ['users.read' => true, 'users.delete' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $role->id);
    });

    it('refuses to write a key the editor does not hold into the matrix', function () {
        /*
         * The other half of ARC-03: refusing the key on a person's overrides is pointless if the
         * same account can add it to a role — their own, if they like — and take it that way.
         */
        $actor = actingAsWithRole(['permissions.update']);
        $role = createRole('Admin');

        $this->actingAs($actor)->patchJson("/api/auth/roles/{$role->id}/permissions", [
            'permissions' => ['tips.manage' => true],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.permissions.0', 'rolesAndPermissions.cannotGrantUnheldPermission');

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_key' => 'tips.manage',
            'value' => true,
        ]);
    });

    /*
     * The administrator is the root of the grant cap: nobody can hand out a key they do not hold,
     * so a key that leaves this role can never come back. A manager without `design.read` could
     * untick it here and lock the design surface for everybody, the administrator included.
     */
    it('refuses to take a key off the administrator, whoever asks', function () {
        $this->seed(RoleSeeder::class);
        $administrator = Role::where('slug', 'administrator')->firstOrFail();

        $manager = actingAsWithRole(['permissions.read', 'permissions.update']);
        $everything = User::factory()->create(['role_id' => $administrator->id]);

        foreach ([$manager, $everything] as $actor) {
            $this->actingAs($actor)
                ->patchJson("/api/auth/roles/{$administrator->id}/permissions", ['permissions' => ['design.read' => false]])
                ->assertStatus(422)
                ->assertJsonPath('errors.permissions.0', 'rolesAndPermissions.administratorKeepsEveryKey');
        }

        expect(RolePermission::where('role_id', $administrator->id)->where('permission_key', 'design.read')->value('value'))->toBeTruthy();
    });

    it('restores an administrator matrix that already lost a key', function () {
        /*
         * The state the bug left behind: `design.read` gone from the administrator, and the
         * administrator therefore unable to grant it back under the cap. Granting TO this role is
         * exempt from the cap, and any save writes the whole catalogue back.
         */
        $this->seed(RoleSeeder::class);
        $administrator = Role::where('slug', 'administrator')->firstOrFail();
        RolePermission::where('role_id', $administrator->id)->where('permission_key', 'design.read')->update(['value' => false]);
        RolePermission::where('role_id', $administrator->id)->where('permission_key', 'tips.manage')->delete();

        $owner = User::factory()->create(['role_id' => $administrator->id]);

        $this->actingAs($owner)
            ->patchJson("/api/auth/roles/{$administrator->id}/permissions", ['permissions' => ['design.read' => true]])
            ->assertOk();

        $declaredKeys = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->sort()->values()->all();
        $held = RolePermission::where('role_id', $administrator->id)->where('value', true)
            ->pluck('permission_key')->sort()->values()->all();

        expect($held)->toBe($declaredKeys);
    });

    it('still lets a key be taken off any other role', function () {
        $actor = actingAsWithRole(['permissions.update']);
        $role = createRole('Moderators');
        RolePermission::create(['role_id' => $role->id, 'permission_key' => 'design.read', 'value' => true]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['design.read' => false]])
            ->assertOk();
    });

    it('rejects unauthenticated requests', function () {
        $role = createRole('Admin');

        $response = $this->patchJson("/api/auth/roles/{$role->id}/permissions", [
            'permissions' => ['users.read' => true],
        ]);

        $response->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/auth/roles/{id}
// ---------------------------------------------------------------------------

describe('DELETE /api/auth/roles/{id}', function () {
    it('deletes a role when authenticated', function () {
        $actor = actingAsWithRole(['permissions.update']);
        $role = createRole('Deletable Role');

        $response = $this->actingAs($actor)->deleteJson("/api/auth/roles/{$role->id}");

        $response->assertOk();
        expect(Role::find($role->id))->toBeNull();
    });

    it('rejects a signed-in account without permissions.update', function () {
        $role = createRole('Protected Role');

        $this->actingAs(actingAsWithRole(['permissions.read']))
            ->deleteJson("/api/auth/roles/{$role->id}")
            ->assertForbidden();

        expect(Role::find($role->id))->not->toBeNull();
    });

    it('rejects unauthenticated requests', function () {
        $role = createRole('Protected Role');

        $response = $this->deleteJson("/api/auth/roles/{$role->id}");

        $response->assertUnauthorized();
    });

    it('returns 404 for unknown role', function () {
        $actor = actingAsWithRole(['permissions.update']);

        $response = $this->actingAs($actor)->deleteJson('/api/auth/roles/999999');

        $response->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// Seeded roles
// ---------------------------------------------------------------------------

describe('the seeded member role', function () {
    it('has no say over roles, or every key it is denied would be one grant away', function () {
        $this->seed(ManagerSeeder::class);

        $keys = RolePermission::query()
            ->where('role_id', Role::where('slug', 'user')->value('id'))
            ->pluck('permission_key')
            ->all();

        expect($keys)->not->toContain('permissions.update')
            ->and($keys)->not->toContain('permissions.read')
            ->and($keys)->toContain('home.read');
    });
});
