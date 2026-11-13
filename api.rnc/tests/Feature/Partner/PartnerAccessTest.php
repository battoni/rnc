<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\LinkedAccountService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\PartnerAccessEvent;
use App\Modules\Partner\Services\PartnerAccessService;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/*
 * A partner's portal login (rnc-2 F1, R1–R2, A1; plan AM1, AM5, AM9):
 *
 *   POST   /api/partners/{id}/access            `{username, email}` → the login and its password, once (201)
 *   POST   /api/partners/{id}/access/password   a new password, once; the old sessions end
 *   DELETE /api/partners/{id}/access            the login deactivated and its sessions ended
 *
 * All behind `partners.manage` (ceo, operador). One active login per partner (409
 * `partner_access_exists`); the user is born in the partner's firm with role `parceiro`; every
 * grant and revoke writes one `partner_access_events` row, which F4's subscription months read.
 */
function accessFirmId(): int
{
    return (int) Firm::where('slug', 'franco')->value('id');
}

function accessManager(string $role = 'ceo'): User
{
    return User::factory()->create([
        'firm_id' => accessFirmId(),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function accessPartner(array $overrides = []): Partner
{
    return Partner::withoutGlobalScopes()->create(array_merge([
        'firm_id' => accessFirmId(),
        'name' => 'NexoERP (exemplo)',
        'specialty' => 'erp_nfe',
        'email' => 'contato3@parceiro.exemplo',
        'active' => true,
    ], $overrides));
}

/** @return array<string, mixed> the access the endpoint answered */
function grantAccess(Partner $partner, string $username = 'parceiro.3', string $email = 'portal3@parceiro.exemplo'): array
{
    return test()->actingAs(accessManager())
        ->postJson("/api/partners/{$partner->id}/access", ['username' => $username, 'email' => $email])
        ->assertCreated()
        ->json('data');
}

/**
 * A fresh client: the guards a previous request resolved (an `actingAs`) are forgotten, as a
 * browser of its own would start without them.
 */
function accessForgetSignedIn(): void
{
    auth()->forgetGuards();
    app()->forgetInstance('auth.driver');
}

function accessLogin(string $username, string $password): TestResponse
{
    accessForgetSignedIn();

    return test()->postJson('/api/auth/login', ['username' => $username, 'password' => $password]);
}

/** A stored session row of the user's, as `SESSION_DRIVER=database` writes one at sign-in. */
function accessSessionRow(int $userId): void
{
    DB::table('sessions')->insert([
        'id' => 'session-'.$userId.'-'.random_int(1, 1_000_000),
        'user_id' => $userId,
        'payload' => '',
        'last_activity' => time(),
    ]);
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09T13:00:00Z'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('creating the access (A1)', function () {
    it('creates a parceiro login of the partner, in its firm, and shows the password once', function () {
        $partner = accessPartner();

        $access = grantAccess($partner);

        $user = User::where('username', 'parceiro.3')->firstOrFail();

        expect($access)->toMatchArray(['username' => 'parceiro.3', 'email' => 'portal3@parceiro.exemplo', 'active' => true, 'user_id' => $user->id])
            ->and($access['password'])->toBeString()->toHaveLength(16)
            ->and($user->partner_id)->toBe($partner->id)
            ->and($user->firm_id)->toBe(accessFirmId())
            ->and($user->role->slug)->toBe('parceiro')
            ->and($user->status)->toBe(UserStatus::ACTIVE)
            ->and($user->password)->not->toBe($access['password'])
            ->and(Hash::check($access['password'], $user->password))->toBeTrue()
            ->and($partner->fresh()->access_granted_at?->toIso8601String())->toBe('2026-11-09T13:00:00+00:00');

        accessLogin('parceiro.3', $access['password'])->assertOk()->assertJsonPath('data.partner_id', $partner->id);
    });

    it('writes one granted event (AM9)', function () {
        $partner = accessPartner();
        $access = grantAccess($partner);

        $events = PartnerAccessEvent::withoutGlobalScopes()->get();

        expect($events)->toHaveCount(1)
            ->and($events[0]->only(['firm_id', 'partner_id', 'user_id', 'type']))->toBe(['firm_id' => accessFirmId(), 'partner_id' => $partner->id, 'user_id' => $access['user_id'], 'type' => 'granted']);
    });

    it('never answers the password again', function () {
        $partner = accessPartner();
        grantAccess($partner);

        $listed = $this->actingAs(accessManager())->getJson('/api/partners')->assertOk()->json('data.0');

        expect($listed['access'])->toMatchArray(['username' => 'parceiro.3', 'active' => true])
            ->and($listed['access'])->not->toHaveKey('password');
    });

    it('refuses a second active login of the same partner with 409', function () {
        $partner = accessPartner();
        grantAccess($partner);

        $this->actingAs(accessManager())
            ->postJson("/api/partners/{$partner->id}/access", ['username' => 'parceiro.outro', 'email' => 'outro@parceiro.exemplo'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'partner_access_exists');

        expect(User::where('partner_id', $partner->id)->count())->toBe(1);
    });

    it('answers a concurrent second grant with 409, through the unique partner_id', function () {
        $partner = accessPartner();
        $this->mock(LinkedAccountService::class, function ($mock) {
            $mock->shouldReceive('findPartnerAccount')->andReturnNull();
            $mock->shouldReceive('openAccount')->andThrow(postgresUniqueViolation('users'));
        });

        $this->actingAs(accessManager())
            ->postJson("/api/partners/{$partner->id}/access", ['username' => 'parceiro.3', 'email' => 'portal3@parceiro.exemplo'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'partner_access_exists');

        expect(PartnerAccessEvent::withoutGlobalScopes()->count())->toBe(0);
    });

    it('follows the users uniqueness of username and e-mail', function () {
        User::factory()->create(['username' => 'reinaldo', 'email' => 'reinaldo@exemplo.com.br']);
        $partner = accessPartner();

        $this->actingAs(accessManager())
            ->postJson("/api/partners/{$partner->id}/access", ['username' => 'reinaldo', 'email' => 'REINALDO@exemplo.com.br'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email']);
    });

    it('refuses an inactive partner', function () {
        $partner = accessPartner(['active' => false]);

        $this->actingAs(accessManager())
            ->postJson("/api/partners/{$partner->id}/access", ['username' => 'parceiro.3', 'email' => 'portal3@parceiro.exemplo'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'partner_inactive');
    });

    it('answers 404 for another firm\'s partner', function () {
        $foreign = accessPartner(['firm_id' => Firm::factory()->create()->id]);

        $this->actingAs(accessManager())
            ->postJson("/api/partners/{$foreign->id}/access", ['username' => 'parceiro.3', 'email' => 'portal3@parceiro.exemplo'])
            ->assertNotFound();

        expect(User::where('partner_id', $foreign->id)->exists())->toBeFalse();
    });
});

describe('regenerating the password (A1, AM5)', function () {
    it('changes it, ends the old sessions, and only the new one signs in', function () {
        $partner = accessPartner();
        $first = grantAccess($partner);
        accessSessionRow($first['user_id']);

        $second = $this->actingAs(accessManager())->postJson("/api/partners/{$partner->id}/access/password")->assertOk()->json('data');

        expect($second['password'])->toHaveLength(16)->not->toBe($first['password'])
            ->and(DB::table('sessions')->where('user_id', $first['user_id'])->count())->toBe(0);

        accessLogin('parceiro.3', $first['password'])->assertUnprocessable()->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
        accessLogin('parceiro.3', $second['password'])->assertOk();
    });

    it('answers 404 when the partner has no active login', function () {
        $partner = accessPartner();

        $this->actingAs(accessManager())->postJson("/api/partners/{$partner->id}/access/password")
            ->assertNotFound()
            ->assertJsonPath('code', 'partner_access_missing');
    });
});

describe('revoking the access (A1, AM5)', function () {
    it('deactivates the login: the right password then answers auth.accountInactive', function () {
        $partner = accessPartner();
        $access = grantAccess($partner);
        accessSessionRow($access['user_id']);

        $this->actingAs(accessManager())->deleteJson("/api/partners/{$partner->id}/access")
            ->assertOk()
            ->assertJsonPath('data.active', false);

        expect(User::find($access['user_id'])->status)->toBe(UserStatus::INACTIVE)
            ->and(DB::table('sessions')->where('user_id', $access['user_id'])->count())->toBe(0)
            ->and($partner->fresh()->access_revoked_at?->toIso8601String())->toBe('2026-11-09T13:00:00+00:00')
            ->and(PartnerAccessEvent::withoutGlobalScopes()->pluck('type')->all())->toBe(['granted', 'revoked']);

        accessLogin('parceiro.3', $access['password'])->assertUnprocessable()->assertJsonPath('errors.username.0', 'auth.accountInactive');
    });

    it('turns the revoked user\'s live cookie into a 401 on its next request', function () {
        config(['session.driver' => 'database']);
        $partner = accessPartner();
        $access = grantAccess($partner);

        $cookieName = config('session.cookie');
        $signedIn = accessLogin('parceiro.3', $access['password'])->assertOk();
        $sessionId = $signedIn->getCookie($cookieName, decrypt: false)?->getValue();

        expect($sessionId)->toBeString()
            ->and(DB::table('sessions')->where('user_id', $access['user_id'])->count())->toBe(1);

        accessForgetSignedIn();
        $this->withUnencryptedCookie($cookieName, $sessionId)->getJson('/api/portal/me')->assertOk();

        app(PartnerAccessService::class)->revoke($partner->id);

        accessForgetSignedIn();
        $this->withUnencryptedCookie($cookieName, $sessionId)->getJson('/api/portal/me')->assertUnauthorized();
    });

    it('answers 404 when there is nothing to revoke', function () {
        $partner = accessPartner();

        $this->actingAs(accessManager())->deleteJson("/api/partners/{$partner->id}/access")
            ->assertNotFound()
            ->assertJsonPath('code', 'partner_access_missing');
    });

    it('lets a revoked partner be granted again, on the same login', function () {
        $partner = accessPartner();
        $first = grantAccess($partner);
        $this->actingAs(accessManager())->deleteJson("/api/partners/{$partner->id}/access")->assertOk();

        $again = grantAccess($partner, 'parceiro.3', 'portal3@parceiro.exemplo');

        expect($again['user_id'])->toBe($first['user_id'])
            ->and(User::where('partner_id', $partner->id)->count())->toBe(1)
            ->and(User::find($first['user_id'])->status)->toBe(UserStatus::ACTIVE)
            ->and($partner->fresh()->access_revoked_at)->toBeNull()
            ->and(PartnerAccessEvent::withoutGlobalScopes()->orderBy('id')->pluck('type')->all())->toBe(['granted', 'revoked', 'granted']);

        accessLogin('parceiro.3', $again['password'])->assertOk();
    });
});

describe('deactivating the partner (R2, A1)', function () {
    it('deactivates its login too, with a revoked event', function () {
        $partner = accessPartner();
        $access = grantAccess($partner);
        accessSessionRow($access['user_id']);

        $this->actingAs(accessManager())->patchJson("/api/partners/{$partner->id}", ['active' => false])->assertOk();

        expect(User::find($access['user_id'])->status)->toBe(UserStatus::INACTIVE)
            ->and(DB::table('sessions')->where('user_id', $access['user_id'])->count())->toBe(0)
            ->and(PartnerAccessEvent::withoutGlobalScopes()->pluck('type')->all())->toBe(['granted', 'revoked']);

        accessLogin('parceiro.3', $access['password'])->assertJsonPath('errors.username.0', 'auth.accountInactive');
    });

    it('leaves a partner without a login untouched', function () {
        $partner = accessPartner();

        $this->actingAs(accessManager())->patchJson("/api/partners/{$partner->id}", ['active' => false])->assertOk();

        expect(PartnerAccessEvent::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('who may manage it (A1)', function () {
    it('refuses the team every access endpoint with 403', function () {
        $partner = accessPartner();
        $member = accessManager('equipe');

        $this->actingAs($member)->postJson("/api/partners/{$partner->id}/access", ['username' => 'parceiro.3', 'email' => 'p@parceiro.exemplo'])->assertForbidden();
        $this->actingAs($member)->postJson("/api/partners/{$partner->id}/access/password")->assertForbidden();
        $this->actingAs($member)->deleteJson("/api/partners/{$partner->id}/access")->assertForbidden();

        expect(User::where('partner_id', $partner->id)->exists())->toBeFalse();
    });

    it('lets the operator manage it as well', function () {
        $partner = accessPartner();

        $this->actingAs(accessManager('operador'))
            ->postJson("/api/partners/{$partner->id}/access", ['username' => 'parceiro.3', 'email' => 'portal3@parceiro.exemplo'])
            ->assertCreated();
    });
});
