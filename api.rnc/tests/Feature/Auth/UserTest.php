<?php

use App\Helpers\Pagination;
use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// GET /api/auth/users
// ---------------------------------------------------------------------------

describe('GET /api/auth/users', function () {
    it('returns users list for an authorised user', function () {
        $actor = userWithPermissions(['users.read']);
        User::factory()->count(3)->create();

        $this->actingAs($actor)
            ->getJson('/api/auth/users')
            ->assertOk()
            ->assertJsonStructure(['data' => ['current_page', 'last_page', 'per_page', 'total', 'data' => [['id', 'username']]]]);
    });

    it('pages the list and clamps per_page to the ceiling', function () {
        $actor = userWithPermissions(['users.read']);
        User::factory()->count(4)->create();

        $this->actingAs($actor)
            ->getJson('/api/auth/users?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5)
            ->assertJsonCount(2, 'data.data');

        $this->actingAs($actor)
            ->getJson('/api/auth/users?per_page=100000')
            ->assertOk()
            ->assertJsonPath('data.per_page', Pagination::MAX_PER_PAGE);
    });

    it('filters by profile_type when provided', function () {
        config()->set('registration.profile_types', [
            'member' => 'user',
            'partner' => 'user',
        ]);

        $actor = userWithPermissions(['users.read']);
        $member = User::factory()->create(['profile_type' => 'member']);
        User::factory()->create(['profile_type' => 'partner']);

        $data = $this->actingAs($actor)
            ->getJson('/api/auth/users?profile_type=member')
            ->assertOk()
            ->json('data.data');

        expect($data)->toHaveCount(1)
            ->and($data[0]['id'])->toBe($member->id);
    });

    it('returns 403 when user lacks users.read permission', function () {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/auth/users')
            ->assertForbidden();
    });

    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/auth/users')->assertUnauthorized();
    });

    it('filters by ids when provided', function () {
        $actor = userWithPermissions(['users.read']);
        $target = User::factory()->create();
        User::factory()->count(2)->create();

        $response = $this->actingAs($actor)
            ->getJson("/api/auth/users?ids[]={$target->id}")
            ->assertOk();

        $data = $response->json('data.data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['id'])->toBe($target->id);
    });
});

// ---------------------------------------------------------------------------
// GET /api/auth/users/{id}
// ---------------------------------------------------------------------------

describe('GET /api/auth/users/{id}', function () {
    it('returns a single user for an authorised actor', function () {
        $actor = userWithPermissions(['users.read']);
        $target = User::factory()->create(['username' => 'dave']);

        $this->actingAs($actor)
            ->getJson("/api/auth/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.username', 'dave');
    });

    it('says whether the e-mail is proven, without handing out the timestamp', function () {
        $actor = userWithPermissions(['users.read']);
        $verified = User::factory()->create(['email_verified_at' => now()]);
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($actor)
            ->getJson("/api/auth/users/{$verified->id}")
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonMissingPath('data.email_verified_at');

        $this->actingAs($actor)
            ->getJson("/api/auth/users/{$unverified->id}")
            ->assertJsonPath('data.email_verified', false);
    });

    it('returns 404 when user does not exist', function () {
        $actor = userWithPermissions(['users.read']);

        $this->actingAs($actor)
            ->getJson('/api/auth/users/99999')
            ->assertNotFound();
    });

    it('returns 403 when actor lacks users.read', function () {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->getJson("/api/auth/users/{$target->id}")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Escalation: what a person who can edit accounts may hand out
// ---------------------------------------------------------------------------

describe('granting more than you hold', function () {
    /*
     * `users.create` / `users.update` used to be the whole product: whoever could edit an account
     * could grant it — or themselves, on their own row — `permissions.update`, or simply assign the
     * administrator role. Editing people and deciding what people may do are different jobs
     * (ARC-03).
     */
    it('refuses an override the editor does not hold themselves', function () {
        $actor = userWithPermissions(['users.read', 'users.update']);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['permissions' => ['permissions.update' => true]])
            ->assertStatus(422)
            ->assertJsonPath('errors.permissions.0', 'rolesAndPermissions.cannotGrantUnheldPermission');

        expect($target->userPermissions()->count())->toBe(0);
    });

    it('ends every session of a person whose password another account changes, as a reset does (verify PE3)', function () {
        $actor = userWithPermissions(['users.read', 'users.update', 'users.reset_password']);
        $target = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'target-a', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'actor-a', 'user_id' => $actor->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['password' => 'outra-senha-forte'])
            ->assertOk();

        expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $actor->id)->count())->toBe(1);
    });

    it("keeps the person's sessions when the edit leaves the password alone (verify PE3)", function () {
        $actor = userWithPermissions(['users.read', 'users.update']);
        $target = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'target-b', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($actor)->patchJson("/api/auth/users/{$target->id}", ['name' => 'Outro'])->assertOk();

        expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(1);
    });

    it('allows an override the editor does hold', function () {
        $actor = userWithPermissions(['users.read', 'users.update', 'leads.update']);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['permissions' => ['leads.update' => true]])
            ->assertOk();

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $target->id,
            'permission_key' => 'leads.update',
            'value' => true,
        ]);
    });

    it('lets the editor take a key away that they never held', function () {
        $actor = userWithPermissions(['users.read', 'users.update']);
        $target = User::factory()->create();

        // Revoking is not an escalation, and an administrator who was never given a key still has
        // every reason to take it off somebody else.
        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['permissions' => ['permissions.update' => false]])
            ->assertOk();
    });

    it('refuses a role that grants more than the editor holds', function () {
        $actor = userWithPermissions(['users.read', 'users.update']);
        $target = User::factory()->create();
        $powerful = Role::create(['name' => 'Power', 'slug' => 'power']);
        RolePermission::create(['role_id' => $powerful->id, 'permission_key' => 'permissions.update', 'value' => true]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['role_id' => $powerful->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.role_id.0', 'rolesAndPermissions.cannotAssignUnheldRole');

        expect($target->refresh()->role_id)->not->toBe($powerful->id);
    });

    it('allows a role whose keys the editor holds, and one that grants nothing', function () {
        $actor = userWithPermissions(['users.read', 'users.update', 'leads.update']);
        $target = User::factory()->create();
        $tips = Role::create(['name' => 'Tips', 'slug' => 'leads']);
        RolePermission::create(['role_id' => $tips->id, 'permission_key' => 'leads.update', 'value' => true]);
        $empty = Role::create(['name' => 'Empty', 'slug' => 'empty']);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['role_id' => $tips->id])
            ->assertOk();

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['role_id' => $empty->id])
            ->assertOk();
    });

    it('refuses the same grant on create', function () {
        $actor = userWithPermissions(['users.create']);
        $powerful = Role::create(['name' => 'Power', 'slug' => 'power']);
        RolePermission::create(['role_id' => $powerful->id, 'permission_key' => 'permissions.update', 'value' => true]);

        $this->actingAs($actor)
            ->postJson('/api/auth/users', [
                'name' => 'Eve',
                'last_name' => 'Green',
                'username' => 'eve',
                'role_id' => $powerful->id,
                'password' => 'password123',
                'status' => UserStatus::ACTIVE->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.role_id.0', 'rolesAndPermissions.cannotAssignUnheldRole');

        $this->assertDatabaseMissing('users', ['username' => 'eve']);
    });
});

// ---------------------------------------------------------------------------
// Nobody edits an account that holds more than they do
// ---------------------------------------------------------------------------

describe('an account above the editor', function () {
    /*
     * A manager without `design.read` could still take power AWAY from the administrator: a denying
     * override on their row (a personal override beats the role), a smaller profile, a deactivation
     * — and then nobody held the key to put it back. The cap on grants did not reach that; this does.
     */
    beforeEach(function () {
        $this->manager = userWithPermissions(['users.read', 'users.update', 'users.archive', 'users.delete', 'leads.read']);
        $this->above = userWithPermissions(['users.read', 'referrals.read']);
    });

    dataset('edits of an account above', [
        'rename' => [fn () => ['name' => 'Renamed']],
        'deny a key it holds' => [fn () => ['permissions' => ['referrals.read' => false]]],
        'move it to a smaller profile' => [fn () => ['role_id' => Role::create(['name' => 'Smaller', 'slug' => 'smaller'])->id]],
        'deactivate it' => [fn () => ['status' => UserStatus::INACTIVE->value]],
    ]);

    it('refuses to edit it', function (Closure $payload) {
        $this->actingAs($this->manager)
            ->patchJson("/api/auth/users/{$this->above->id}", $payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.id.0', 'users.cannotManageHigherAccount');

        expect($this->above->refresh()->name)->not->toBe('Renamed')
            ->and($this->above->status)->toBe(UserStatus::ACTIVE);
    })->with('edits of an account above');

    it('refuses to archive it', function () {
        $this->actingAs($this->manager)
            ->patchJson("/api/auth/users/{$this->above->id}/archive")
            ->assertStatus(422)
            ->assertJsonPath('errors.id.0', 'users.cannotManageHigherAccount');

        expect($this->above->refresh()->status)->toBe(UserStatus::ACTIVE);
    });

    it('refuses to delete it', function () {
        $this->above->update(['status' => UserStatus::INACTIVE]);

        $this->actingAs($this->manager)
            ->deleteJson("/api/auth/users/{$this->above->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.id.0', 'users.cannotManageHigherAccount');

        expect(User::find($this->above->id))->not->toBeNull();
    });

    it('still edits an account that holds no more than the editor', function () {
        $peer = userWithPermissions(['users.read', 'leads.read']);

        $this->actingAs($this->manager)
            ->patchJson("/api/auth/users/{$peer->id}", ['name' => 'Renamed'])
            ->assertOk();
    });

    it('still lets the editor edit their own row', function () {
        $this->actingAs($this->manager)
            ->patchJson("/api/auth/users/{$this->manager->id}", ['name' => 'Myself'])
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// POST /api/auth/users
// ---------------------------------------------------------------------------

describe('POST /api/auth/users', function () {
    it('creates and returns a new user', function () {
        $actor = userWithPermissions(['users.create']);
        $role = Role::create(['name' => 'Test Role', 'slug' => 'test-role']);

        $payload = [
            'name' => 'Eve',
            'last_name' => 'Green',
            'username' => 'eve',
            'role_id' => $role->id,
            'password' => 'password123',
            'status' => UserStatus::ACTIVE->value,
        ];

        $this->actingAs($actor)
            ->postJson('/api/auth/users', $payload)
            ->assertCreated()
            ->assertJsonPath('data.username', 'eve');

        $this->assertDatabaseHas('users', ['username' => 'eve']);
    });

    it('returns 403 when actor lacks users.create', function () {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/auth/users', ['username' => 'x'])
            ->assertForbidden();
    });

    it('returns 422 when required fields are missing', function () {
        $actor = userWithPermissions(['users.create']);

        $this->actingAs($actor)
            ->postJson('/api/auth/users', [])
            ->assertUnprocessable();
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/auth/users/{id}
// ---------------------------------------------------------------------------

describe('PATCH /api/auth/users/{id}', function () {
    it('updates and returns the modified user', function () {
        $actor = userWithPermissions(['users.update']);
        $target = User::factory()->create(['name' => 'Frank']);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['name' => 'Franklin'])
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Franklin']);
    });

    it('refuses to let an administrator deactivate their own account via edit', function () {
        $actor = userWithPermissions(['users.update']);
        $actor->update(['status' => UserStatus::ACTIVE]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$actor->id}", ['status' => UserStatus::INACTIVE->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'users.form.cannotDeactivateSelf']);

        $this->assertDatabaseHas('users', ['id' => $actor->id, 'status' => UserStatus::ACTIVE->value]);
    });

    it('still lets an administrator edit the rest of their own account', function () {
        $actor = userWithPermissions(['users.update']);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$actor->id}", ['name' => 'Renamed', 'status' => UserStatus::ACTIVE->value])
            ->assertOk();
    });

    it('returns 404 when target user does not exist', function () {
        $actor = userWithPermissions(['users.update']);

        $this->actingAs($actor)
            ->patchJson('/api/auth/users/99999', ['name' => 'Nobody'])
            ->assertNotFound();
    });

    it('returns 403 when actor lacks users.update', function () {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}", ['name' => 'X'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/auth/users/{id}/archive
// ---------------------------------------------------------------------------

describe('PATCH /api/auth/users/{id}/archive', function () {
    it('toggles status from active to inactive', function () {
        $actor = userWithPermissions(['users.archive']);
        $target = User::factory()->create(['status' => UserStatus::ACTIVE]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::INACTIVE->value);
    });

    it('toggles status from inactive to active', function () {
        $actor = userWithPermissions(['users.archive']);
        $target = User::factory()->create(['status' => UserStatus::INACTIVE]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$target->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value);
    });

    it('refuses to let an administrator deactivate their own account', function () {
        $actor = userWithPermissions(['users.archive']);
        $actor->update(['status' => UserStatus::ACTIVE]);

        $this->actingAs($actor)
            ->patchJson("/api/auth/users/{$actor->id}/archive")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'users.form.cannotDeactivateSelf']);

        $this->assertDatabaseHas('users', ['id' => $actor->id, 'status' => UserStatus::ACTIVE->value]);
    });

    it('returns 404 when user does not exist', function () {
        $actor = userWithPermissions(['users.archive']);

        $this->actingAs($actor)
            ->patchJson('/api/auth/users/99999/archive')
            ->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/auth/users/{id}
// ---------------------------------------------------------------------------

describe('DELETE /api/auth/users/{id}', function () {
    it('deletes the user and returns the resource', function () {
        $actor = userWithPermissions(['users.delete']);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/auth/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('returns 404 when user does not exist', function () {
        $actor = userWithPermissions(['users.delete']);

        $this->actingAs($actor)
            ->deleteJson('/api/auth/users/99999')
            ->assertNotFound();
    });

    it('returns 403 when actor lacks users.delete', function () {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/auth/users/{$target->id}")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// GET /api/auth/users/check-username
// ---------------------------------------------------------------------------

// One click from locking the only administrator out of the product, with nobody left who could let
// them back in. The API refuses rather than trusting the interface to hide the button.
describe('deleting yourself', function () {
    it('refuses to delete the account making the request', function () {
        $actor = userWithPermissions(['users.delete']);

        $this->actingAs($actor)
            ->deleteJson("/api/auth/users/{$actor->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.id.0', 'users.cannotDeleteSelf');

        expect(User::find($actor->id))->not->toBeNull();
    });

    it('still lets an administrator delete somebody else', function () {
        $actor = userWithPermissions(['users.delete']);
        $other = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/auth/users/{$other->id}")
            ->assertOk();

        expect(User::find($other->id))->toBeNull();
    });
});

describe('GET /api/auth/users/check-username', function () {
    it('returns available when username is not taken', function () {
        $actor = userWithPermissions(['users.create']);

        $this->actingAs($actor)
            ->getJson('/api/auth/users/check-username?username=unused_name')
            ->assertOk()
            ->assertJsonPath('data.available', true);
    });

    it('returns 409 when username is already taken', function () {
        $actor = userWithPermissions(['users.create']);
        User::factory()->create(['username' => 'taken_name']);

        $this->actingAs($actor)
            ->getJson('/api/auth/users/check-username?username=taken_name')
            ->assertConflict();
    });

    it('returns 422 when username query param is missing', function () {
        $actor = userWithPermissions(['users.create']);

        $this->actingAs($actor)
            ->getJson('/api/auth/users/check-username')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    });

    it('excludes the specified id from the uniqueness check', function () {
        $actor = userWithPermissions(['users.create']);
        $existing = User::factory()->create(['username' => 'my_name']);

        $this->actingAs($actor)
            ->getJson("/api/auth/users/check-username?username=my_name&exclude_id={$existing->id}")
            ->assertOk()
            ->assertJsonPath('data.available', true);
    });
});
