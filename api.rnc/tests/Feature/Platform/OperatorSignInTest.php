<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Testing\TestResponse;

/*
 * rnc-4 F4a — A26 (R24; plan AM1). The platform operator `battoni` has no firm: it signs in at the
 * root only, `auth/me` says so (`firm` null, `platform.is_operator` true), and outside view-as
 * (F4c) every firm-scoped endpoint answers the platform firm's rows — never Franco's, whatever the
 * header or the id it sends.
 */
beforeEach(function () {
    config(['seeding.superadmin_password' => 'operator-secret', 'firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class, SuperAdminSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->hub = Firm::where('slug', PlatformFirmSeeder::PLATFORM_SLUG)->firstOrFail();
});

function operatorSignIn(?string $firmHeader = null): TestResponse
{
    auth()->forgetGuards();
    $request = test()->flushHeaders();

    if ($firmHeader !== null) {
        $request = $request->withHeader('X-Firm', $firmHeader);
    }

    return $request->postJson('/api/auth/login', ['username' => 'battoni', 'password' => 'operator-secret']);
}

it('seeds battoni as the operator with no firm', function () {
    $battoni = User::where('username', 'battoni')->firstOrFail();

    expect($battoni->firm_id)->toBeNull()
        ->and($battoni->role?->slug)->toBe('operador');
});

it('signs in at the root, and me says no firm and the operator', function () {
    operatorSignIn()
        ->assertOk()
        ->assertJsonPath('data.firm', null)
        ->assertJsonPath('data.platform.is_operator', true);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'battoni')
        ->assertJsonPath('data.firm', null)
        ->assertJsonPath('data.firm_slug', null)
        ->assertJsonPath('data.platform.is_operator', true);
});

it("is refused on a firm's host, with no session", function () {
    operatorSignIn(FirmSeeder::FRANCO_SLUG)->assertStatus(422);

    $this->flushHeaders()->getJson('/api/auth/me')->assertUnauthorized();
});

describe('signed in, outside view-as', function () {
    beforeEach(function () {
        $this->francoLead = Lead::factory()->create(['firm_id' => $this->franco->id]);
        $this->hubLead = Lead::factory()->create(['firm_id' => $this->hub->id]);
        $this->francoMember = User::factory()->create([
            'firm_id' => $this->franco->id,
            'role_id' => Role::where('slug', 'equipe')->value('id'),
        ]);

        operatorSignIn()->assertOk();
    });

    it("reads the platform firm's leads only", function () {
        $ids = collect($this->getJson('/api/leads')->assertOk()->json('data.columns'))
            ->flatMap(fn (array $column) => array_column($column['items'], 'id'))
            ->all();

        expect($ids)->toContain($this->hubLead->id)
            ->and($ids)->not->toContain($this->francoLead->id);
    });

    it("answers 404 on Franco's lead by id, read or written", function () {
        $this->getJson("/api/leads/{$this->francoLead->id}")->assertNotFound();
        $this->postJson("/api/leads/{$this->francoLead->id}/notes", ['text' => 'nota do operador'])->assertNotFound();

        expect($this->francoLead->fresh()->firm_id)->toBe($this->franco->id);
    });

    it("is refused with 403 when it names Franco's header", function () {
        $this->withHeader('X-Firm', FirmSeeder::FRANCO_SLUG)->getJson('/api/leads')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    });

    it('lists no Franco user and answers 404 on one by id', function () {
        $usernames = collect($this->getJson('/api/auth/users')->assertOk()->json('data.data'))->pluck('username')->all();

        expect($usernames)->not->toContain($this->francoMember->username);

        $this->getJson("/api/auth/users/{$this->francoMember->id}")->assertNotFound();
        $this->patchJson("/api/auth/users/{$this->francoMember->id}", ['name' => 'Trocado'])->assertNotFound();
    });
});
