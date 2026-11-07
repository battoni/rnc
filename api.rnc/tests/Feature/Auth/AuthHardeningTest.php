<?php

/*
 * The overnight review of 2026-09-17 probed the Auth API with real requests and found doors the
 * interface kept shut but the API left open: an account with only `users.update` changed the
 * administrator's password (probe Q1), a user override stored a permission nobody declared (P3),
 * the e-mail code could be guessed without a ceiling (P9), a role that does not exist answered
 * 500, the users list ran one query per row (P1), a refusal came back as English prose (P4) and
 * any member could ask which usernames exist (P2). Each test below keeps one of those shut.
 */

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use App\Modules\Auth\DTOs\RoleStoreDTO;
use App\Modules\Auth\Models\PendingRegistration;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Auth\Repositories\PendingRegistrationRepository;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

function hardeningRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'firstName' => 'Bia',
        'lastName' => 'Lima',
        'email' => 'bia@example.test',
        'password' => 'segredo-forte',
        'password_confirmation' => 'segredo-forte',
        'cpf' => '390.533.447-05',
        'birthDate' => '1991-05-20',
        'termsAccepted' => true,
    ], $overrides);
}

function hardeningRegisterAndReadCode(): string
{
    Mail::fake();

    test()->postJson('/api/auth/register', hardeningRegistrationPayload())->assertCreated();

    $code = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

/**
 * A real repository with one method made to fail. Laravel's `partialMock` skips the constructor,
 * and these repositories need theirs (the model they query).
 */
function hardeningFailingRepository(string $class, array $constructorArguments, string $method): void
{
    $repository = Mockery::mock($class, $constructorArguments)->makePartial();
    $repository->shouldReceive($method)->andThrow(new RuntimeException('disk full'));

    app()->instance($class, $repository);
}

/** A wrong six-digit code, guaranteed different from the one that was sent. */
function hardeningWrongCode(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}

describe('changing a password through the users screen', function () {
    it('refuses an editor without users.reset_password, and the password stays', function () {
        $editor = userWithPermissions(['users.read', 'users.update']);
        $administrator = User::factory()->create(['password' => 'original-secret']);

        $this->actingAs($editor)
            ->patchJson("/api/auth/users/{$administrator->id}", ['password' => 'hijacked1'])
            ->assertForbidden()
            ->assertJsonPath('error', 'users.form.passwordResetPermissionRequired');

        expect(Hash::check('original-secret', $administrator->fresh()->password))->toBeTrue();
    });

    it('lets an editor with users.reset_password change it', function () {
        $editor = userWithPermissions(['users.update', 'users.reset_password']);
        $target = User::factory()->create();

        $this->actingAs($editor)
            ->patchJson("/api/auth/users/{$target->id}", ['password' => 'brand-new-1'])
            ->assertOk();

        expect(Hash::check('brand-new-1', $target->fresh()->password))->toBeTrue();
    });

    it('still lets an editor without it save the rest, with the password left empty', function () {
        $editor = userWithPermissions(['users.update']);
        $target = User::factory()->create(['name' => 'Before']);

        $this->actingAs($editor)
            ->patchJson("/api/auth/users/{$target->id}", ['name' => 'After', 'password' => null])
            ->assertOk();

        expect($target->fresh()->name)->toBe('After');
    });
});

describe('per-user permission overrides', function () {
    it('refuses a key no permission group declares, on create', function () {
        $role = Role::create(['name' => 'Plain']);

        $this->actingAs(userWithPermissions(['users.create']))
            ->postJson('/api/auth/users', [
                'name' => 'New',
                'last_name' => 'Person',
                'username' => 'new_person',
                'role_id' => $role->id,
                'status' => 'active',
                'password' => 'long-enough',
                'permissions' => ['billing.refund' => true],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'rolesAndPermissions.unknownPermission']);

        expect(User::where('username', 'new_person')->exists())->toBeFalse();
    });

    it('refuses a key no permission group declares, on update', function () {
        $target = User::factory()->create();

        $this->actingAs(userWithPermissions(['users.update']))
            ->patchJson("/api/auth/users/{$target->id}", ['permissions' => ['billing.refund' => true]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'rolesAndPermissions.unknownPermission']);

        expect(UserPermission::where('permission_key', 'billing.refund')->exists())->toBeFalse();
    });

    it('still accepts declared keys', function () {
        $target = User::factory()->create();

        // The actor holds what it hands out: since ARC-03 a grant is capped by the giver's own keys.
        $this->actingAs(userWithPermissions(['users.update', 'home.read']))
            ->patchJson("/api/auth/users/{$target->id}", ['permissions' => ['home.read' => true]])
            ->assertOk();

        expect(UserPermission::where('user_id', $target->id)->where('permission_key', 'home.read')->exists())->toBeTrue();
    });

    it('names the rule with a key on the role matrix too', function () {
        $role = Role::create(['name' => 'Matrix']);

        $this->actingAs(userWithPermissions(['permissions.update']))
            ->patchJson("/api/auth/roles/{$role->id}/permissions", ['permissions' => ['billing.refund' => true]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'rolesAndPermissions.unknownPermission']);
    });
});

describe('guessing the e-mail verification code', function () {
    it('burns the code on the fifth wrong guess, so the right one stops working', function () {
        $code = hardeningRegisterAndReadCode();
        $wrong = hardeningWrongCode($code);

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/auth/email/verify', ['email' => 'bia@example.test', 'code' => $wrong])
                ->assertJsonValidationErrors(['code' => 'auth.flow.signUp.verify.invalidCode']);
        }

        $this->postJson('/api/auth/email/verify', ['email' => 'bia@example.test', 'code' => $wrong])
            ->assertJsonValidationErrors(['code' => 'auth.flow.signUp.verify.codeLocked']);

        $this->postJson('/api/auth/email/verify', ['email' => 'bia@example.test', 'code' => $code])
            ->assertUnprocessable();

        expect(User::where('email', 'bia@example.test')->exists())->toBeFalse();
    });

    it('counts the guesses on the row and starts over with a new code', function () {
        $code = hardeningRegisterAndReadCode();

        $this->postJson('/api/auth/email/verify', ['email' => 'bia@example.test', 'code' => hardeningWrongCode($code)])
            ->assertUnprocessable();

        expect(PendingRegistration::where('email', 'bia@example.test')->value('attempts'))->toBe(1);

        $this->postJson('/api/auth/email/code', ['email' => 'bia@example.test'])->assertOk();

        expect(PendingRegistration::where('email', 'bia@example.test')->value('attempts'))->toBe(0);
    });

    it('throttles one address even when every guess comes from a new machine', function () {
        RateLimiter::clear('registration-verify');
        hardeningRegisterAndReadCode();

        $statuses = collect(range(1, 11))->map(fn (int $attempt) => $this
            ->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->postJson('/api/auth/email/verify', ['email' => 'BIA@example.test', 'code' => '123456'])
            ->status());

        expect($statuses->last())->toBe(429);
    });
});

describe('a role that does not exist', function () {
    it('answers 404 to a matrix write, and writes nothing', function () {
        $this->actingAs(userWithPermissions(['permissions.update', 'users.read']))
            ->patchJson('/api/auth/roles/99999/permissions', ['permissions' => ['users.read' => true]])
            ->assertNotFound();

        expect(RolePermission::where('role_id', 99999)->exists())->toBeFalse();
    });

    it('answers 404 to an edit that names groups', function () {
        $this->actingAs(userWithPermissions(['permissions.update']))
            ->patchJson('/api/auth/roles/99999', ['name' => 'Ghost', 'permission_group_ids' => ['users']])
            ->assertNotFound();
    });
});

describe('the users list', function () {
    it('runs the same number of queries whatever the number of roles on the page', function () {
        $actor = userWithPermissions(['users.read']);

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/auth/users?per_page=50')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        User::factory()->create(['role_id' => Role::create(['name' => 'Role 0'])->id]);
        $this->actingAs($actor);
        $withOneRole = $countQueries();

        foreach (range(1, 5) as $index) {
            User::factory()->create(['role_id' => Role::create(['name' => "Role {$index}"])->id]);
        }

        expect($countQueries())->toBe($withOneRole);
    });
});

describe('writes that must land together', function () {
    it('rolls the user edit back when the permissions fail to save', function () {
        $target = User::factory()->create(['name' => 'Before']);

        hardeningFailingRepository(UserPermissionRepository::class, [new UserPermission], 'syncUserPermissions');

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs(userWithPermissions(['users.update', 'home.read']))
            ->patchJson("/api/auth/users/{$target->id}", ['name' => 'After', 'permissions' => ['home.read' => true]]))
            ->toThrow(RuntimeException::class);

        expect($target->fresh()->name)->toBe('Before');
    });

    it('rolls the new user back when the permissions fail to save', function () {
        $role = Role::create(['name' => 'Plain']);

        hardeningFailingRepository(UserPermissionRepository::class, [new UserPermission], 'syncUserPermissions');

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs(userWithPermissions(['users.create', 'home.read']))
            ->postJson('/api/auth/users', [
                'name' => 'New',
                'last_name' => 'Person',
                'username' => 'half_made',
                'role_id' => $role->id,
                'status' => 'active',
                'password' => 'long-enough',
                'permissions' => ['home.read' => true],
            ]))
            ->toThrow(RuntimeException::class);

        expect(User::where('username', 'half_made')->exists())->toBeFalse();
    });

    it('rolls the new role back when its matrix fails to save', function () {
        hardeningFailingRepository(RoleRepository::class, [new Role], 'syncRolePermissions');

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs(userWithPermissions(['permissions.update']))
            ->postJson('/api/auth/roles', ['name' => 'Half Role', 'permission_group_ids' => ['users']]))
            ->toThrow(RuntimeException::class);

        expect(Role::where('name', 'Half Role')->exists())->toBeFalse();
    });

    it('rolls the role edit back when its matrix fails to save', function () {
        $role = Role::create(['name' => 'Stable']);

        hardeningFailingRepository(RoleRepository::class, [new Role], 'upsertRolePermissions');

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs(userWithPermissions(['permissions.update']))
            ->patchJson("/api/auth/roles/{$role->id}", ['name' => 'Renamed', 'permission_group_ids' => ['users']]))
            ->toThrow(RuntimeException::class);

        expect($role->fresh()->name)->toBe('Stable');
    });

    it('rolls the account back when the pending row cannot be cleared, with verification off', function () {
        config()->set('registration.verify_email', false);

        hardeningFailingRepository(PendingRegistrationRepository::class, [new PendingRegistration], 'deletePendingRegistrationByEmail');

        $this->withoutExceptionHandling();

        expect(fn () => $this->postJson('/api/auth/register', hardeningRegistrationPayload()))
            ->toThrow(RuntimeException::class);

        expect(User::where('email', 'bia@example.test')->exists())->toBeFalse();
    });
});

describe('a role refusal', function () {
    it('names a taken name with a key', function () {
        Role::create(['name' => 'Taken']);

        $this->actingAs(userWithPermissions(['permissions.update']))
            ->postJson('/api/auth/roles', ['name' => 'Taken', 'permission_group_ids' => ['users']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'rolesAndPermissions.form.nameTaken']);
    });

    it('names a taken name with a key when the unique index catches a race', function () {
        Role::create(['name' => 'Raced', 'slug' => 'other-slug']);

        try {
            app(RoleService::class)->create(new RoleStoreDTO(name: 'Raced', description: null, permission_group_ids: ['users']));
            $this->fail('A taken name was stored.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['name' => ['rolesAndPermissions.form.nameTaken']]);
        }
    });

    it('names a taken name with a key on rename', function () {
        Role::create(['name' => 'Taken']);
        $role = Role::create(['name' => 'Free']);

        $this->actingAs(userWithPermissions(['permissions.update']))
            ->patchJson("/api/auth/roles/{$role->id}", ['name' => 'Taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'rolesAndPermissions.form.nameTaken']);
    });

    it('refuses to delete the administrator role, with a key', function () {
        $role = Role::create(['name' => 'Operador', 'slug' => 'operador']);

        $this->actingAs(userWithPermissions(['permissions.update']))
            ->deleteJson("/api/auth/roles/{$role->id}")
            ->assertForbidden()
            ->assertJsonPath('error', 'rolesAndPermissions.cannotDeleteAdministratorRole');

        expect(Role::find($role->id))->not->toBeNull();
    });

    it('refuses to delete a role somebody holds, naming them, with a key', function () {
        $role = Role::create(['name' => 'Held']);
        User::factory()->create(['role_id' => $role->id, 'name' => 'Carla', 'last_name' => 'Dias']);

        $this->actingAs(userWithPermissions(['permissions.update']))
            ->deleteJson("/api/auth/roles/{$role->id}")
            ->assertConflict()
            ->assertJsonPath('error', 'rolesAndPermissions.cannotDeleteRole')
            ->assertJsonPath('users', ['Carla Dias']);

        expect(Role::find($role->id))->not->toBeNull();
    });
});

describe('asking whether a username is free', function () {
    it('refuses a member who cannot create or edit users', function () {
        User::factory()->create(['username' => 'battoni']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/auth/users/check-username?username=battoni')
            ->assertForbidden();
    });

    it('answers an editor, naming a taken username with a key', function (string $permission) {
        User::factory()->create(['username' => 'battoni']);

        $this->actingAs(userWithPermissions([$permission]))
            ->getJson('/api/auth/users/check-username?username=battoni')
            ->assertConflict()
            ->assertJsonPath('error', 'users.form.usernameTaken');
    })->with(['users.create', 'users.update']);

    it('validates the query through a request', function () {
        $this->actingAs(userWithPermissions(['users.create']))
            ->getJson('/api/auth/users/check-username?username=someone&exclude_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exclude_id']);
    });
});

describe('the support names on the forgot-password screen', function () {
    it('stays public but is throttled per address', function () {
        RateLimiter::clear('support-names');

        $statuses = collect(range(1, 11))->map(fn () => $this
            ->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->getJson('/api/auth/support-names')
            ->status());

        expect($statuses->first())->toBe(200)
            ->and($statuses->last())->toBe(429);
    });
});
