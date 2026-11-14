<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\PendingRegistration;
use App\Modules\Auth\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// Registration is closed in this product (rnc-4 R7, D21); these tests prove the flow a product of the
// family gets when it opens it. Closed by default: `RegistrationClosedTest`.
beforeEach(fn () => config(['registration.enabled' => true]));

// ---------------------------------------------------------------------------

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'firstName' => 'Ana',
        'lastName' => 'Souza',
        'email' => 'ana@example.test',
        'password' => 'segredo-forte',
        'password_confirmation' => 'segredo-forte',
        'cpf' => '390.533.447-05',
        'birthDate' => '1990-04-17',
        'termsAccepted' => true,
    ], $overrides);
}

/**
 * Registers, then reads the code out of the mail that was sent. The code exists nowhere
 * else — only its hash is stored — so a test that wants to verify has to receive the
 * message like a person would.
 */
function registerAndReadCode(array $overrides = []): string
{
    Mail::fake();

    test()->postJson('/api/auth/register', registrationPayload($overrides))->assertCreated();

    $codigo = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$codigo) {
        $codigo = $mail->code;

        return true;
    });

    return $codigo;
}

// ---------------------------------------------------------------------------

it('parks the registration instead of creating a user', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.test')
        ->assertJsonPath('data.name', 'Ana Souza');

    // The account does not exist yet, and that is the point: an unproved address must not
    // take the unique e-mail, or one typo locks the real owner out of signing up.
    expect(User::where('email', 'ana@example.test')->exists())->toBeFalse()
        ->and(PendingRegistration::where('email', 'ana@example.test')->exists())->toBeTrue();

    Mail::assertSent(EmailVerificationCodeMail::class);
});

it('never stores the code, only its hash', function () {
    $codigo = registerAndReadCode();

    $pending = PendingRegistration::where('email', 'ana@example.test')->first();

    expect($pending->code_hash)->not->toBe($codigo)
        ->and(Hash::check($codigo, $pending->code_hash))->toBeTrue();
});

it('creates the user when the code checks out, and signs them in', function () {
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);

    $codigo = registerAndReadCode();

    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigo])
        ->assertOk()
        // `UserResource` carries `username`, not `email` — and the registration writes the
        // address into it, because that is what the login flow looks a person up by.
        ->assertJsonPath('data.username', 'ana@example.test');

    $user = User::where('email', 'ana@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->role_id)->toBe(Role::where('slug', 'user')->value('id'))
        // The document and the date travel from the pending row to the user. Without
        // `phone`, `profile_type`, `cpf` and `birth_date` in the model's fillable, Eloquent
        // drops them without a word and the account is born missing what the person typed.
        ->and($user->cpf)->toBe('39053344705')
        ->and($user->birth_date->format('Y-m-d'))->toBe('1990-04-17')
        ->and($user->profile_type)->toBe('user')
        // The pending row is gone in the same transaction — a code that worked once is
        // not a code that works twice.
        ->and(PendingRegistration::where('email', 'ana@example.test')->exists())->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

it('keeps the password the person typed, not a hash of a hash', function () {
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);

    $codigo = registerAndReadCode();
    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigo])->assertOk();

    // Re-hashing the already-hashed pending password would lock the person out of the
    // account they just created, and every test above would still be green.
    expect(Hash::check('segredo-forte', User::where('email', 'ana@example.test')->value('password')))->toBeTrue();
});

it('refuses a wrong code and keeps the registration pending', function () {
    registerAndReadCode();

    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect(PendingRegistration::where('email', 'ana@example.test')->exists())->toBeTrue()
        ->and(User::where('email', 'ana@example.test')->exists())->toBeFalse();
});

it('refuses an expired code', function () {
    $codigo = registerAndReadCode();

    PendingRegistration::where('email', 'ana@example.test')->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigo])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('answers the same whether or not a registration is pending', function () {
    Mail::fake();

    $conhecido = $this->postJson('/api/auth/email/code', ['email' => 'ana@example.test']);
    $desconhecido = $this->postJson('/api/auth/email/code', ['email' => 'ninguem@example.test']);

    // Two different answers here would turn this endpoint into a way to ask whether an
    // address is registered.
    expect($conhecido->status())->toBe($desconhecido->status())
        ->and($conhecido->json())->toBe($desconhecido->json());

    Mail::assertNothingSent();
});

it('re-sends a code for a pending registration', function () {
    registerAndReadCode();

    Mail::fake();
    $this->postJson('/api/auth/email/code', ['email' => 'ana@example.test'])->assertOk();

    Mail::assertSent(EmailVerificationCodeMail::class);
});

it('refuses to register an e-mail that already has an account', function () {
    User::factory()->create(['email' => 'ana@example.test']);

    $this->postJson('/api/auth/register', registrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('lets a second attempt on a waiting address do nothing but re-send the owner a code', function () {
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);

    registerAndReadCode();

    /*
     * The takeover this refuses: a stranger registers somebody else's address with a password of
     * their own, and the code that lands in the victim's inbox creates an account the stranger
     * knows the password to. So the second attempt may not touch what is stored — only re-send.
     */
    Mail::fake();
    $this->postJson('/api/auth/register', registrationPayload([
        'firstName' => 'Estranho',
        'password' => 'senha-do-estranho',
        'password_confirmation' => 'senha-do-estranho',
        'cpf' => '153.509.460-56',
    ]))->assertCreated();

    $pending = PendingRegistration::where('email', 'ana@example.test')->firstOrFail();
    expect($pending->first_name)->toBe('Ana')
        ->and(Hash::check('segredo-forte', $pending->password))->toBeTrue()
        ->and(Hash::check('senha-do-estranho', $pending->password))->toBeFalse();

    $codigoNovo = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$codigoNovo) {
        $codigoNovo = $mail->code;

        return true;
    });

    // And the account the owner ends up with is theirs: their name, their password.
    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigoNovo])->assertOk();

    $user = User::where('email', 'ana@example.test')->firstOrFail();
    expect($user->name)->toBe('Ana')
        ->and(Hash::check('segredo-forte', $user->password))->toBeTrue();
});

it('keeps a waiting registration intact even after verification is switched off', function () {
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);

    registerAndReadCode();

    /*
     * A row that was waiting when the product turned verification off. Falling through would
     * overwrite it with the second attempt's data and promote that straight to an account.
     */
    config()->set('registration.verify_email', false);

    $this->postJson('/api/auth/register', registrationPayload([
        'firstName' => 'Estranho',
        'password' => 'senha-do-estranho',
        'password_confirmation' => 'senha-do-estranho',
        'cpf' => '153.509.460-56',
    ]));

    expect(PendingRegistration::where('email', 'ana@example.test')->value('first_name'))->toBe('Ana')
        ->and(User::where('email', 'ana@example.test')->exists())->toBeFalse();
});

it('caps re-sends per e-mail, however many addresses ask', function () {
    registerAndReadCode();

    // Distinct client addresses, one inbox: the per-address ceiling alone never trips.
    $statuses = collect(range(1, 4))->map(fn (int $attempt) => $this
        ->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
        ->postJson('/api/auth/email/code', ['email' => 'ana@example.test'])
        ->status());

    expect($statuses->last())->toBe(429);
});

it('lets an abandoned attempt go so the address can be used again', function () {
    registerAndReadCode();
    PendingRegistration::where('email', 'ana@example.test')->update(['expires_at' => now()->subDay()]);

    Mail::fake();
    // A different person now registers a different address; the sweep runs on the way in.
    $this->postJson('/api/auth/register', registrationPayload(['email' => 'outra@example.test']))->assertCreated();

    expect(PendingRegistration::where('email', 'ana@example.test')->exists())->toBeFalse();
});

it('stores the document as digits, whatever mask the screen showed', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', registrationPayload())->assertCreated();

    // Two people typing the same document with and without dots must collide, not coexist.
    expect(PendingRegistration::where('email', 'ana@example.test')->value('cpf'))->toBe('39053344705');
});

it('refuses a document that already belongs to an account', function () {
    User::factory()->create(['cpf' => '39053344705']);

    $this->postJson('/api/auth/register', registrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('cpf');
});

it('demands terms and a confirmed password', function () {
    $this->postJson('/api/auth/register', registrationPayload(['termsAccepted' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('termsAccepted');

    $this->postJson('/api/auth/register', registrationPayload(['password_confirmation' => 'outra-coisa']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('renders the message, which faking it never proves', function () {
    /*
     * `Mail::fake()` intercepts before the Mailable is built, so every test above stays
     * green with a missing blade file or a broken one. This is the only test here that
     * actually renders it, and the reason it exists.
     */
    $mail = new EmailVerificationCodeMail('123456');
    $html = $mail->render();

    // The chrome as well as the code: a table-based layout is what survives an e-mail
    // client, and losing it is a silent regression — the message still sends, it just
    // arrives as an unstyled column in Outlook.
    expect($html)->toContain('123456')
        ->and($html)->toContain('<table')
        ->and($mail->envelope()->subject)->not->toBeEmpty();

    // And the text alternative, which nothing else in this file would notice missing.
    $content = $mail->content();
    $text = view($content->text, array_merge($mail->buildViewData(), $content->with))->render();

    expect($text)->toContain('123456')
        ->and($text)->not->toContain('<table');
});

// ── the switches ───────────────────────────────────────────────────────────

it('creates the user straight away when verification is off', function () {
    config()->set('registration.verify_email', false);
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);
    Mail::fake();

    $this->postJson('/api/auth/register', registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.username', 'ana@example.test');

    expect(User::where('email', 'ana@example.test')->exists())->toBeTrue()
        ->and(PendingRegistration::where('email', 'ana@example.test')->exists())->toBeFalse();

    Mail::assertNothingSent();
    $this->assertAuthenticated();
});

it('parks the account for approval when the product asks for it', function () {
    config()->set('registration.new_user_status', UserStatus::INACTIVE->value);
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);

    $codigo = registerAndReadCode();
    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigo])->assertOk();

    expect(User::where('email', 'ana@example.test')->first()->status)->toBe(UserStatus::INACTIVE);

    // Parked means parked: the account exists and has no session. Signing it in handed it the
    // whole app, and the approval step held nobody back.
    $this->assertGuest();
});

it('asks nothing about the profile when there is only one', function () {
    Mail::fake();

    // No `profileType` in the payload at all — the screen never showed the question.
    $this->postJson('/api/auth/register', registrationPayload())->assertCreated();

    expect(PendingRegistration::where('email', 'ana@example.test')->value('profile_type'))->toBe('user');
});

it('demands the profile once there is a choice, and only accepts one on the list', function () {
    config()->set('registration.profile_types', ['user' => 'user', 'manager' => 'manager']);
    Mail::fake();

    $this->postJson('/api/auth/register', registrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('profileType');

    // The allow-list is the API's, not the screen's: a hand-made request cannot ask for a
    // role the product never offered.
    $this->postJson('/api/auth/register', registrationPayload(['profileType' => 'administrator']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('profileType');

    $this->postJson('/api/auth/register', registrationPayload(['profileType' => 'manager']))->assertCreated();
});

it('gives the role the chosen profile maps to', function () {
    config()->set('registration.profile_types', ['user' => 'user', 'manager' => 'manager']);
    Role::query()->create(['name' => 'User', 'slug' => 'user', 'description' => 'Default']);
    $manager = Role::query()->create(['name' => 'Manager', 'slug' => 'manager', 'description' => 'Manages']);

    $codigo = registerAndReadCode(['profileType' => 'manager']);
    $this->postJson('/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => $codigo])->assertOk();

    expect(User::where('email', 'ana@example.test')->value('role_id'))->toBe($manager->id);
});
