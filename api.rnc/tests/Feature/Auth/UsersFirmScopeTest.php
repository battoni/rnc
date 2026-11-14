<?php

use App\Models\User;
use App\Modules\Auth\Contracts\LoginCodeSender;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;

/*
 * rnc-4 F1a — plan AM2 (R37). Users are read inside the current firm: an administrator of firm A
 * never lists, opens, edits, archives or deletes an account of firm B — the answer is 404, as if it
 * did not exist — and the public doors (support names, phone code) name only the firm's own people.
 * Username and e-mail stay unique across every firm.
 */
const USERS_SCOPE_PHONE = '5511977776666';

/** @param list<string> $keys */
function usersScopeGrant(User $user, array $keys): User
{
    foreach ($keys as $key) {
        UserPermission::create(['user_id' => $user->id, 'permission_key' => $key, 'value' => true]);
    }

    return $user;
}

beforeEach(function () {
    $this->seed(FirmSeeder::class);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);

    $this->admin = usersScopeGrant(
        User::factory()->create(['firm_id' => $this->franco->id, 'name' => 'Admin', 'last_name' => 'Franco']),
        ['users.read', 'users.create', 'users.update', 'users.archive', 'users.delete', 'users.reset_password'],
    );
    $this->colleague = User::factory()->create(['firm_id' => $this->franco->id, 'name' => 'Colega']);
    $this->stranger = usersScopeGrant(
        User::factory()->create(['firm_id' => $this->alvorada->id, 'name' => 'Helena', 'last_name' => 'Alvorada', 'phone' => USERS_SCOPE_PHONE]),
        ['users.reset_password'],
    );
});

describe("an administrator of one firm, against another firm's account", function () {
    it('never lists it', function () {
        $ids = collect($this->actingAs($this->admin)->getJson('/api/auth/users?per_page=50')->assertOk()->json('data.data'))->pluck('id');

        expect($ids)->toContain($this->admin->id, $this->colleague->id)
            ->not->toContain($this->stranger->id);
    });

    it('gets a 404 opening it', function () {
        $this->actingAs($this->admin)->getJson("/api/auth/users/{$this->stranger->id}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/api/auth/users/{$this->colleague->id}")->assertOk();
    });

    it('gets a 404 editing it, and the account is untouched', function () {
        $this->actingAs($this->admin)->patchJson("/api/auth/users/{$this->stranger->id}", ['name' => 'Tomada'])->assertNotFound();

        expect($this->stranger->fresh()->name)->toBe('Helena');
    });

    it('gets a 404 archiving it, and the account stays active', function () {
        $this->actingAs($this->admin)->patchJson("/api/auth/users/{$this->stranger->id}/archive")->assertNotFound();

        expect($this->stranger->fresh()->status->value)->toBe('active');
    });

    it('gets a 404 deleting it, and the account still exists', function () {
        $this->actingAs($this->admin)->deleteJson("/api/auth/users/{$this->stranger->id}")->assertNotFound();

        expect(User::find($this->stranger->id))->not->toBeNull();
    });

    it('creates an account in its own firm', function () {
        $this->actingAs($this->admin)->postJson('/api/auth/users', [
            'name' => 'Nova',
            'last_name' => 'Pessoa',
            'username' => 'nova.pessoa',
            'role_id' => Role::create(['name' => 'Escopo', 'slug' => 'escopo'])->id,
            'password' => 'segredo-forte',
            'status' => 'active',
        ])->assertCreated();

        expect(User::where('username', 'nova.pessoa')->value('firm_id'))->toBe($this->franco->id);
    });

    it('still finds a username taken in another firm', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/auth/users/check-username?username='.$this->stranger->username)
            ->assertStatus(409);
    });
});

describe('the public doors of a firm host', function () {
    it("name only the firm's own support people", function () {
        $names = $this->withHeader('X-Firm', 'franco')->getJson('/api/auth/support-names')->assertOk()->json('data');

        expect($names)->toContain('Admin Franco')->not->toContain('Helena Alvorada');
    });

    it("never send a code for another firm's phone", function () {
        config(['auth.login_code.expose' => true]);
        $sender = new class implements LoginCodeSender
        {
            public int $sent = 0;

            public function send(string $phone, string $code): bool
            {
                $this->sent++;

                return true;
            }
        };
        app()->instance(LoginCodeSender::class, $sender);

        $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/auth/phone/code', ['phone' => USERS_SCOPE_PHONE])
            ->assertOk()
            ->assertJsonPath('data.dev_code', null);

        expect($sender->sent)->toBe(0);
    });
});
