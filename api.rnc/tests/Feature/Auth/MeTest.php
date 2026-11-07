<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * `PATCH auth/me` exists so a person can fix their own name without holding `users.update` — the
 * permission that also grants editing everybody else. The whole point is the size of the list it
 * accepts, so that is what these assert.
 */

describe('a person editing themselves', function () {
    it('changes their own name and gets the updated account back', function () {
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'Silva']);

        $this->actingAs($user)
            ->patchJson('/api/auth/me', ['name' => 'Ana Paula', 'last_name' => 'Souza'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana Paula')
            ->assertJsonPath('data.last_name', 'Souza');

        expect($user->fresh()->name)->toBe('Ana Paula');
    });

    it('needs no permission at all', function () {
        $user = User::factory()->create(['role_id' => null]);

        $this->actingAs($user)->patchJson('/api/auth/me', ['name' => 'Sem papel'])->assertOk();
    });

    it('refuses a guest', function () {
        $this->patchJson('/api/auth/me', ['name' => 'Ninguém'])->assertUnauthorized();
    });

    it('cannot blank the name it sends', function () {
        $user = User::factory()->create(['name' => 'Ana']);

        $this->actingAs($user)
            ->patchJson('/api/auth/me', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    /*
     * The one that matters. Role, status and the permission matrix are decisions somebody else
     * makes about this account; if they rode in here, "change my name" and "grant myself
     * everything" would be the same request.
     */
    it('ignores role, status and permissions sent along with the name', function () {
        $role = Role::create(['name' => 'Administrator QA']);
        $user = User::factory()->create(['role_id' => null, 'status' => UserStatus::ACTIVE->value]);

        $this->actingAs($user)
            ->patchJson('/api/auth/me', [
                'name' => 'Ana',
                'role_id' => $role->id,
                'status' => UserStatus::INACTIVE->value,
                'permissions' => ['users.delete' => true],
            ])
            ->assertOk();

        $fresh = $user->fresh();

        expect($fresh->role_id)->toBeNull()
            ->and($fresh->status)->toBe(UserStatus::ACTIVE)
            ->and($fresh->userPermissions()->count())->toBe(0);
    });
});
