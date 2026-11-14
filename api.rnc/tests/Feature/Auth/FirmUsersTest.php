<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * A36 (rnc-4 R31; plan F5a, AM2): the firm's manager (`gestao`) manages the firm's team logins —
 * list, create, edit, (de)activate, reset a password — and nothing past that boundary. The firm
 * scoping of every user read landed in F1a (`UsersFirmScopeTest`); this file holds the manager's
 * grants and the create rules, and the negative side first: a manager never makes or turns anyone
 * into an `operador` or a `parceiro`, never names the firm or the partner of an account, never
 * reaches an account outside its firm — another firm's, or the firm-less platform operator's — and
 * never touches the roles screen (`permissions.*`) nor deletes.
 */
const FIRM_USERS_PASSWORD = 'segredo-forte-1';

/** @param array<string, mixed> $overrides */
function firmUserPayload(string $role, array $overrides = []): array
{
    return [
        'name' => 'Nova',
        'last_name' => 'Pessoa',
        'username' => 'nova.pessoa',
        'role_id' => Role::where('slug', $role)->value('id'),
        'password' => FIRM_USERS_PASSWORD,
        'status' => 'active',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, PlatformFirmSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);
    $this->manager = assistantPerson('gestao', attributes: ['username' => 'gestao.franco']);
    $this->member = assistantPerson('equipe', attributes: ['username' => 'equipe.franco', 'name' => 'Patrícia']);
    $this->stranger = assistantPerson('equipe', firm: $this->alvorada, attributes: ['username' => 'equipe.alvorada', 'name' => 'Lívia']);
    $this->operator = User::factory()->create([
        'firm_id' => null,
        'username' => 'operador.plataforma',
        'name' => 'Operador',
        'role_id' => Role::where('slug', 'operador')->value('id'),
    ]);
});

describe('a manager, at the edge of what it may do (negative first)', function () {
    it('cannot create an operador, and no account is made', function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload('operador'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');

        expect(User::where('username', 'nova.pessoa')->exists())->toBeFalse();
    });

    it('cannot create a parceiro login (that is the partner screen\'s, with its partner)', function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload('parceiro'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');

        expect(User::where('username', 'nova.pessoa')->exists())->toBeFalse();
    });

    it('cannot hand out operador or parceiro even holding every key of the role through overrides', function (string $role) {
        $keys = RolePermission::where('role_id', Role::where('slug', $role)->value('id'))->where('value', true)->pluck('permission_key');

        foreach ($keys as $key) {
            UserPermission::firstOrCreate(['user_id' => $this->manager->id, 'permission_key' => $key], ['value' => true]);
        }

        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload($role))
            ->assertUnprocessable()
            ->assertJsonPath('errors.role_id.0', 'users.form.roleNotAssignable');

        expect(User::where('username', 'nova.pessoa')->exists())->toBeFalse();
    })->with(['operador', 'parceiro']);

    it('cannot name the firm or the partner of a new account: it is made in the manager\'s firm, with no partner', function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload('equipe', [
            'firm_id' => $this->alvorada->id,
            'partner_id' => 999,
        ]))->assertCreated();

        $made = User::where('username', 'nova.pessoa')->sole();

        expect($made->firm_id)->toBe($this->franco->id)
            ->and($made->partner_id)->toBeNull();
    });

    it('cannot move an account to another firm, give it a partner, or make it an operador', function () {
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}", [
            'firm_id' => $this->alvorada->id,
            'partner_id' => 999,
        ])->assertOk();

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}", [
            'role_id' => Role::where('slug', 'operador')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('role_id');

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}", [
            'role_id' => Role::where('slug', 'parceiro')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('role_id');

        $member = $this->member->fresh();

        expect($member->firm_id)->toBe($this->franco->id)
            ->and($member->partner_id)->toBeNull()
            ->and($member->role->slug)->toBe('equipe');
    });

    it('gets a 404 on the firm-less platform operator, and the account is untouched', function () {
        $this->actingAs($this->manager)->getJson("/api/auth/users/{$this->operator->id}")->assertNotFound();
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->operator->id}", ['name' => 'Tomado', 'password' => 'outra-senha-1'])->assertNotFound();
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->operator->id}/archive")->assertNotFound();

        $operator = $this->operator->fresh();

        expect($operator->name)->toBe('Operador')
            ->and($operator->status->value)->toBe('active')
            ->and(Hash::check('outra-senha-1', $operator->password))->toBeFalse();
    });

    it("gets a 404 on another firm's account, and the account is untouched", function () {
        $this->actingAs($this->manager)->getJson("/api/auth/users/{$this->stranger->id}")->assertNotFound();
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->stranger->id}", ['name' => 'Tomada', 'password' => 'outra-senha-1'])->assertNotFound();
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->stranger->id}/archive")->assertNotFound();

        $stranger = $this->stranger->fresh();

        expect($stranger->name)->toBe('Lívia')
            ->and($stranger->status->value)->toBe('active')
            ->and(Hash::check('outra-senha-1', $stranger->password))->toBeFalse();
    });

    it('cannot delete an account', function () {
        $this->actingAs($this->manager)->deleteJson("/api/auth/users/{$this->member->id}")->assertForbidden();

        expect(User::find($this->member->id))->not->toBeNull();
    });

    it('cannot touch the roles screen (permissions.*)', function () {
        $equipe = Role::where('slug', 'equipe')->value('id');

        $this->actingAs($this->manager)->patchJson("/api/auth/roles/{$equipe}/permissions", ['permissions' => ['users.read' => true]])->assertForbidden();
        $this->actingAs($this->manager)->postJson('/api/auth/roles', ['name' => 'Novo', 'slug' => 'novo'])->assertForbidden();
        $this->actingAs($this->manager)->patchJson("/api/auth/roles/{$equipe}", ['name' => 'Renomeado'])->assertForbidden();
        $this->actingAs($this->manager)->deleteJson("/api/auth/roles/{$equipe}")->assertForbidden();

        expect(Role::find($equipe)->name)->toBe('Equipe');
    });

    it('cannot grant a key it does not hold through a personal override', function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload('equipe', [
            'permissions' => ['platform.firms' => true],
        ]))->assertUnprocessable();

        expect(User::where('username', 'nova.pessoa')->exists())->toBeFalse();
    });
});

describe('the team', function () {
    it('gets 403 on the user list and on creating one', function () {
        $this->actingAs($this->member)->getJson('/api/auth/users')->assertForbidden();
        $this->actingAs($this->member)->postJson('/api/auth/users', firmUserPayload('equipe'))->assertForbidden();
    });
});

describe('a manager, managing its team (A36)', function () {
    it("lists only its firm's accounts", function () {
        $ids = collect($this->actingAs($this->manager)->getJson('/api/auth/users?per_page=50')->assertOk()->json('data.data'))->pluck('id');

        expect($ids)->toContain($this->manager->id, $this->member->id)
            ->not->toContain($this->stranger->id)
            ->not->toContain($this->operator->id);
    });

    it('creates an equipe and a gestao login in its firm', function (string $role) {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload($role))
            ->assertCreated()
            ->assertJsonPath('data.username', 'nova.pessoa');

        $made = User::where('username', 'nova.pessoa')->sole();

        expect($made->firm_id)->toBe($this->franco->id)
            ->and($made->role->slug)->toBe($role)
            ->and(Hash::check(FIRM_USERS_PASSWORD, $made->password))->toBeTrue();
    })->with(['equipe', 'gestao']);

    it('creates a specialist when the flag is sent', function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', firmUserPayload('equipe', ['is_specialist' => true]))
            ->assertCreated()
            ->assertJsonPath('data.is_specialist', true);

        $made = User::where('username', 'nova.pessoa')->sole();

        expect(Specialist::withoutGlobalScopes()->where('user_id', $made->id)->value('firm_id'))->toBe($this->franco->id);
    });

    it('edits a member, flags it a specialist, deactivates and reactivates it', function () {
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}", ['name' => 'Patrícia Lima', 'is_specialist' => true])
            ->assertOk()
            ->assertJsonPath('data.is_specialist', true);

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}/archive")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}/archive")->assertOk()->assertJsonPath('data.status', 'active');

        expect($this->member->fresh()->name)->toBe('Patrícia Lima');
    });

    it("resets a member's password", function () {
        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$this->member->id}", ['password' => 'nova-senha-123'])->assertOk();

        expect(Hash::check('nova-senha-123', $this->member->fresh()->password))->toBeTrue();
    });
});

describe('the firm-less platform operator', function () {
    it('still gives the operador role (the refusal is for a firm member only)', function () {
        config(['firms.default_slug' => 'hub']);

        $this->actingAs($this->operator)->postJson('/api/auth/users', firmUserPayload('operador'))->assertCreated();

        expect(User::where('username', 'nova.pessoa')->value('firm_id'))->toBe(Firm::where('slug', 'hub')->value('id'));
    });
});
