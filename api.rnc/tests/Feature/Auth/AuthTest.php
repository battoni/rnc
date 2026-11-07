<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// POST /api/auth/login
// ---------------------------------------------------------------------------

describe('POST /api/auth/login', function () {
    it('returns the authenticated user on valid credentials', function () {
        $user = User::factory()->create([
            'username' => 'alice',
            'password' => Hash::make('secret123'),
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', 'alice');
    });

    /*
     * The three ways to fail must be indistinguishable from outside.
     *
     * They were not: an unknown username failed on `username` with "does not exist" while a
     * wrong password failed on `password` with "is incorrect", so anyone could discover which
     * addresses have accounts by typing them, one at a time, with no password at all.
     */
    it('answers a wrong password exactly as it answers an unknown username', function () {
        User::factory()->create([
            'username' => 'bob',
            'password' => Hash::make('correctpass'),
            'status' => UserStatus::ACTIVE,
        ]);

        $unknown = $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'secret']);
        $wrongPassword = $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrongpass']);

        $unknown->assertUnprocessable()->assertJsonValidationErrors('username');
        $wrongPassword->assertUnprocessable()->assertJsonValidationErrors('username');

        // Same field AND same sentence — one of the two alone still leaks.
        expect($wrongPassword->json('errors'))->toEqual($unknown->json('errors'));
    });

    it('never names the field that was wrong', function () {
        User::factory()->create(['username' => 'bob', 'password' => Hash::make('correctpass')]);

        $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrongpass'])
            ->assertUnprocessable()
            ->assertJsonMissingPath('errors.password');
    });

    /*
     * A deactivated account could sign in. Deactivating someone is the only lever an
     * administrator has for "this person should not be here any more", and it moved nothing.
     *
     * With the right password we name the reason and send them to an administrator: the caller
     * already proved they know the account, so this is not the username-enumeration leak. A wrong
     * password on the same account still looks like an unknown username.
     */
    it('tells a deactivated account with the right password to speak to an administrator', function () {
        User::factory()->create([
            'username' => 'carol',
            'password' => Hash::make('secret123'),
            'status' => UserStatus::INACTIVE,
        ]);

        $this->postJson('/api/auth/login', ['username' => 'carol', 'password' => 'secret123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username')
            ->assertJsonPath('errors.username.0', 'auth.accountInactive');
    });

    it('answers a deactivated account with the wrong password exactly as it answers an unknown one', function () {
        User::factory()->create([
            'username' => 'carol',
            'password' => Hash::make('secret123'),
            'status' => UserStatus::INACTIVE,
        ]);

        $deactivatedWrongPassword = $this->postJson('/api/auth/login', ['username' => 'carol', 'password' => 'wrongpass']);
        $unknown = $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'secret123']);

        expect($deactivatedWrongPassword->json('errors'))->toEqual($unknown->json('errors'));
    });

    /*
     * The message is the same; the TIME must be too. `Hash::check` is deliberately expensive, so
     * skipping it for an unknown username answered in a fraction of the time and the response
     * told you the account did not exist without saying so.
     *
     * Asserted as a ratio rather than a threshold: a CI runner's absolute numbers are noise, but
     * "one path is an order of magnitude faster than the other" is the shape of the leak, and
     * bcrypt at cost 12 dominates everything else in the request either way.
     */
    it('spends the same order of time on an unknown username as on a wrong password', function () {
        User::factory()->create(['username' => 'bob', 'password' => Hash::make('correctpass')]);

        $timeOf = function (string $username): float {
            $start = microtime(true);
            $this->postJson('/api/auth/login', ['username' => $username, 'password' => 'wrongpass']);

            return microtime(true) - $start;
        };

        // Warm the autoloader and the session so the first call does not carry everyone's cost.
        $timeOf('bob');

        $known = $timeOf('bob');
        $unknown = $timeOf('nobody');

        expect($unknown)->toBeGreaterThan($known / 4);
    });

    /*
     * The door had no limit at all, so a list of passwords could be tried against one account as
     * fast as the network allowed. Counted twice: by address, which stops one machine working
     * through many accounts, and by username, which stops many machines working on one — the
     * second is the one an address limit never sees.
     */
    it('locks the door after five attempts from one address', function () {
        User::factory()->create(['username' => 'bob', 'password' => Hash::make('correctpass')]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrong'])
            ->assertStatus(429);
    });

    it('counts one account across addresses, not only one address across accounts', function () {
        User::factory()->create(['username' => 'bob', 'password' => Hash::make('correctpass')]);

        // A different address every time, which is what a distributed attempt looks like.
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'wrong'])
            ->assertStatus(429);
    });

    it('reads the username case-insensitively, so Alice and alice share one budget', function () {
        User::factory()->create(['username' => 'bob', 'password' => Hash::make('correctpass')]);

        foreach (['bob', 'BOB', 'Bob', 'bOb', 'BoB'] as $spelling) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.'.mb_strlen($spelling)])
                ->postJson('/api/auth/login', ['username' => $spelling, 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.7'])
            ->postJson('/api/auth/login', ['username' => 'BOB', 'password' => 'wrong'])
            ->assertStatus(429);
    });

    it('returns 422 when required fields are missing', function () {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    });
});

// ---------------------------------------------------------------------------
// POST /api/auth/logout
// ---------------------------------------------------------------------------

describe('POST /api/auth/logout', function () {
    it('logs out an authenticated user and returns success', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk();
    });

    it('returns 401 when not authenticated', function () {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// GET /api/auth/me
// ---------------------------------------------------------------------------

describe('GET /api/auth/me', function () {
    it('returns the authenticated user', function () {
        $user = User::factory()->create(['username' => 'carol']);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', 'carol');
    });

    it('returns 401 when not authenticated', function () {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    });

    it('includes a permissions array in the response', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'username', 'permissions']]);
    });
});

// ---------------------------------------------------------------------------
// GET /api/auth/support-names
// ---------------------------------------------------------------------------

describe('GET /api/auth/support-names', function () {
    it('returns an empty array when no active users have reset_password permission', function () {
        $this->getJson('/api/auth/support-names')
            ->assertOk()
            ->assertJsonPath('data', []);
    });
});

// ---------------------------------------------------------------------------
// The 401 that used to be a 500
// ---------------------------------------------------------------------------

describe('an unauthenticated request without Accept: application/json', function () {
    /*
     * This is the one nobody sees until production. Laravel answers an AuthenticationException by
     * redirecting to `route('login')` unless the request wants JSON, and this app has no `login`
     * route — so `route()` threw and the 401 became a 500. The SPA sends the header on every
     * call, which is exactly why it stayed hidden: only the address bar, an uptime monitor or a
     * crawler ever asks for HTML.
     */
    it('answers 401 in JSON, not a 500', function () {
        $response = $this->get('/api/auth/me', ['Accept' => 'text/html']);

        $response->assertUnauthorized();
        expect($response->headers->get('content-type'))->toContain('application/json');
    });

    it('answers the same for a route behind a permission', function () {
        $this->get('/api/auth/users', ['Accept' => 'text/html'])->assertUnauthorized();
    });

    /*
     * Scoped to `api/*`: the welcome page is HTML and is entitled to an HTML error. Asserting
     * this keeps the fix from quietly becoming "the whole app answers JSON".
     */
    it('leaves a web ERROR free to answer in HTML', function () {
        // It has to be an error, not a healthy page: `shouldRenderJsonWhen` only decides how an
        // EXCEPTION is rendered, so a 200 exercises none of it. A mutation that widened the
        // scope to every request passed the earlier version of this test unharmed.
        $response = $this->get('/nao-existe', ['Accept' => 'text/html']);

        $response->assertNotFound();
        expect($response->headers->get('content-type'))->not->toContain('application/json');
    });
});

describe('an unknown API route', function () {
    // A caller that asks for HTML still gets JSON under `api/*`: an API has no page to render, and
    // a 404 page would read to a client as a server that answers in a language it cannot parse.
    it('answers in JSON even when the caller asked for HTML', function () {
        $response = $this->get('/api/nao-existe', ['Accept' => 'text/html']);

        $response->assertNotFound();

        expect($response->headers->get('content-type'))->toContain('application/json');
    });
});
