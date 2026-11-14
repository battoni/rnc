<?php

use App\Models\User;
use App\Modules\Auth\Contracts\LoginCodeSender;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;

/*
 * rnc-4 F1a — A3 (R4, D5; plan AM8). An account signs in only on its own firm's host: a right
 * password on another firm's host gets exactly the wrong-password answer and no session, so the
 * door never says "this person exists, elsewhere". The phone code, a partner's portal login and
 * the QA login-as follow the same rule; the platform operator (no firm) signs in only at the root.
 */
const LOGIN_FIRM_PHONE = '5511988887777';

function loginFirmForget(): void
{
    auth()->forgetGuards();
    app()->forgetInstance('auth.driver');
}

function loginFirmAttempt(string $username, ?string $firmHeader, ?string $password = null): TestResponse
{
    loginFirmForget();
    $request = test()->flushHeaders();

    if ($firmHeader !== null) {
        $request = $request->withHeader('X-Firm', $firmHeader);
    }

    return $request->postJson('/api/auth/login', ['username' => $username, 'password' => $password ?? config('seeding.password')]);
}

function loginFirmOperator(): User
{
    return User::factory()->create([
        'username' => 'operador.plataforma',
        'firm_id' => null,
        'role_id' => Role::where('slug', 'operador')->value('id'),
    ]);
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);
});

describe('the password login', function () {
    it("refuses a right password on another firm's host with the wrong-password answer and no session", function () {
        $wrongPassword = loginFirmAttempt('patricia', 'franco', 'senha-errada');
        $otherFirm = loginFirmAttempt('patricia', 'alvorada');

        $otherFirm->assertUnprocessable()->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
        expect($otherFirm->json('errors'))->toEqual($wrongPassword->json('errors'));
        $this->assertGuest();
    });

    it("signs in on the account's own host, with its header or at the default firm", function () {
        loginFirmAttempt('patricia', 'franco')->assertOk();
        $this->assertAuthenticated();

        loginFirmAttempt('patricia', null)->assertOk();
    });

    it('never tells a deactivated account of another firm that it is deactivated', function () {
        User::where('username', 'patricia')->update(['status' => UserStatus::INACTIVE->value]);

        loginFirmAttempt('patricia', 'alvorada')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
    });

    it("refuses a partner's portal login on another firm's host the same way", function () {
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id,
            'name' => 'NexoERP (exemplo)',
            'specialty' => 'erp_nfe',
            'email' => 'contato@parceiro.exemplo',
            'active' => true,
        ]);
        User::factory()->create([
            'username' => 'parceiro.teste',
            'firm_id' => $this->franco->id,
            'partner_id' => $partner->id,
            'role_id' => Role::where('slug', 'parceiro')->value('id'),
        ]);

        loginFirmAttempt('parceiro.teste', 'alvorada')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
        $this->assertGuest();

        loginFirmAttempt('parceiro.teste', 'franco')->assertOk();
    });
});

describe('the platform operator (no firm)', function () {
    beforeEach(function () {
        Firm::factory()->create(['slug' => 'hub', 'is_platform' => true]);
        config(['firms.default_slug' => 'hub']);
    });

    it('signs in at the root', function () {
        loginFirmOperator();

        loginFirmAttempt('operador.plataforma', null)->assertOk();
    });

    it("is refused on a firm's host", function () {
        loginFirmOperator();

        loginFirmAttempt('operador.plataforma', 'franco')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
        $this->assertGuest();
    });

    it('is the only account with no firm that the root lets in', function () {
        User::factory()->create(['username' => 'sem.escritorio', 'firm_id' => null, 'role_id' => Role::where('slug', 'equipe')->value('id')]);

        loginFirmAttempt('sem.escritorio', null)
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');
    });
});

describe('the phone code', function () {
    beforeEach(function () {
        config(['auth.login_code.expose' => true]);
        app()->instance(LoginCodeSender::class, new class implements LoginCodeSender
        {
            public function send(string $phone, string $code): bool
            {
                return true;
            }
        });
        User::where('username', 'patricia')->update(['phone' => LOGIN_FIRM_PHONE]);
    });

    it("refuses a code spent on another firm's host like a wrong code, with no session", function () {
        $code = $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/auth/phone/code', ['phone' => LOGIN_FIRM_PHONE])
            ->assertOk()
            ->json('data.dev_code');

        $this->flushHeaders()->withHeader('X-Firm', 'alvorada')
            ->postJson('/api/auth/phone/verify', ['phone' => LOGIN_FIRM_PHONE, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_code');
        $this->assertGuest();
    });

    it("does not even issue a real code for an account of another firm's host", function () {
        $this->withHeader('X-Firm', 'alvorada')
            ->postJson('/api/auth/phone/code', ['phone' => LOGIN_FIRM_PHONE])
            ->assertOk()
            ->assertJsonPath('data.dev_code', null);
    });

    it("signs in on the account's own host", function () {
        $code = $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/auth/phone/code', ['phone' => LOGIN_FIRM_PHONE])
            ->json('data.dev_code');

        $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/auth/phone/verify', ['phone' => LOGIN_FIRM_PHONE, 'code' => $code])
            ->assertOk();
        $this->assertAuthenticated();
    });
});

describe('the QA login-as (AM8)', function () {
    beforeEach(fn () => app()->detectEnvironment(fn () => 'local'));

    it("signs a member in on their own firm's host", function () {
        get('http://franco.localhost/api/qa/login-as/patricia')->assertRedirect();

        $this->assertAuthenticatedAs(User::where('username', 'patricia')->firstOrFail());
    });

    it("refuses a member on another firm's host, by host or by header", function () {
        get('http://alvorada.localhost/api/qa/login-as/patricia')->assertNotFound();
        $this->assertGuest();

        $this->withHeader('X-Firm', 'alvorada')->get('/api/qa/login-as/patricia')->assertNotFound();
        $this->assertGuest();
    });

    it('lets the operator in only at the root', function () {
        Firm::factory()->create(['slug' => 'hub', 'is_platform' => true]);
        config(['firms.default_slug' => 'hub']);
        $operator = loginFirmOperator();

        get('http://franco.localhost/api/qa/login-as/operador.plataforma')->assertNotFound();
        $this->assertGuest();

        get('http://localhost/api/qa/login-as/operador.plataforma')->assertRedirect();
        $this->assertAuthenticatedAs($operator);
    });

    it('answers 404 on a host that is no firm', function () {
        get('http://xyz.localhost/api/qa/login-as/patricia')->assertNotFound();
        $this->assertGuest();
    });
});
