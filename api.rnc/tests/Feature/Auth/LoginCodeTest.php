<?php

use App\Models\User;
use App\Modules\Auth\Contracts\LoginCodeSender;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\LoginCode;
use App\Modules\Auth\Repositories\LoginCodeRepository;
use App\Modules\Auth\Services\LoginCodeService;
use Database\Factories\LoginCodeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const LOGIN_CODE_PHONE = '5511999998888';
const LOGIN_CODE_MASKED_PHONE = '(11) 99999-8888';

/**
 * A sender that remembers what it was asked to deliver. The code exists nowhere else — only its
 * hash is stored — so a test that wants to verify has to receive it like a person would.
 */
function fakeLoginCodeSender(bool $delivers = true): object
{
    $sender = new class($delivers) implements LoginCodeSender
    {
        /** @var array<int, array{phone: string, code: string}> */
        public array $sent = [];

        public function __construct(private bool $delivers) {}

        public function send(string $phone, string $code): bool
        {
            $this->sent[] = ['phone' => $phone, 'code' => $code];

            return $this->delivers;
        }
    };

    app()->instance(LoginCodeSender::class, $sender);

    return $sender;
}

function phoneUser(array $overrides = []): User
{
    return User::factory()->create(array_merge(['phone' => LOGIN_CODE_PHONE], $overrides));
}

describe('POST /api/auth/phone/code', function () {
    it('issues a code for the account behind the phone, stores only its hash and sends it', function () {
        phoneUser();
        $sender = fakeLoginCodeSender();

        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_MASKED_PHONE])
            ->assertOk()
            ->assertJsonPath('data.phone', LOGIN_CODE_PHONE)
            ->assertJsonPath('data.expires_in', LoginCodeService::CODE_TTL_MINUTES * 60)
            ->assertJsonMissingPath('data.dev_code');

        expect($sender->sent)->toHaveCount(1)
            ->and($sender->sent[0]['phone'])->toBe(LOGIN_CODE_PHONE)
            ->and($sender->sent[0]['code'])->toMatch('/^\d{6}$/');

        $row = LoginCode::query()->sole();

        expect($row->code_hash)->not->toBe($sender->sent[0]['code'])
            ->and(Hash::check($sender->sent[0]['code'], $row->code_hash))->toBeTrue();
    });

    it('answers a phone with no account exactly as it answers one with an account, and sends nothing', function () {
        phoneUser();
        $sender = fakeLoginCodeSender();

        $known = $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])->assertOk();
        $unknown = $this->postJson('/api/auth/phone/code', ['phone' => '5511988887777'])->assertOk();

        expect(array_keys($unknown->json('data')))->toEqual(array_keys($known->json('data')))
            ->and($sender->sent)->toHaveCount(1);
    });

    /*
     * A phone with no account keeps a row too, so it walks the same states as a real one. Without
     * it, waiting ten minutes or guessing five times answered `invalid_code` only for numbers with
     * no account — a way to learn who uses the product.
     */
    it('walks a phone with no account through the same expired and locked answers', function () {
        phoneUser();
        fakeLoginCodeSender();
        $unknownPhone = '5511988887777';

        foreach ([LOGIN_CODE_PHONE, $unknownPhone] as $phone) {
            $this->postJson('/api/auth/phone/code', ['phone' => $phone])->assertOk();
        }

        $answers = [];

        foreach ([LOGIN_CODE_PHONE, $unknownPhone] as $phone) {
            foreach (range(1, LoginCodeService::MAX_ATTEMPTS) as $attempt) {
                $answers[$phone][] = $this->postJson('/api/auth/phone/verify', ['phone' => $phone, 'code' => '000000'])
                    ->json('code');
            }
        }

        expect($answers[$unknownPhone])->toEqual($answers[LOGIN_CODE_PHONE])
            ->and(end($answers[$unknownPhone]))->toBe('code_locked');

        foreach ([LOGIN_CODE_PHONE, $unknownPhone] as $phone) {
            $this->postJson('/api/auth/phone/code', ['phone' => $phone])->assertOk();
        }

        $this->travel(LoginCodeService::CODE_TTL_MINUTES + 1)->minutes();

        foreach ([LOGIN_CODE_PHONE, $unknownPhone] as $phone) {
            $this->postJson('/api/auth/phone/verify', ['phone' => $phone, 'code' => '000000'])
                ->assertJsonPath('code', 'code_expired');
        }
    });

    it('sends no code when two accounts share the phone', function () {
        phoneUser();
        phoneUser();
        $sender = fakeLoginCodeSender();

        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])->assertOk();

        expect($sender->sent)->toBeEmpty();
    });

    it('supersedes every open code, so only the newest one signs in', function () {
        phoneUser();
        $sender = fakeLoginCodeSender();

        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])->assertOk();
        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])->assertOk();

        expect(LoginCode::query()->whereNull('consumed_at')->count())->toBe(1);

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => $sender->sent[0]['code']])
            ->assertUnprocessable();
    });

    it('keeps the open code usable when the new one could not be written', function () {
        phoneUser();
        fakeLoginCodeSender();
        LoginCode::factory()->create();

        $repository = Mockery::mock(LoginCodeRepository::class, [new LoginCode])->makePartial();
        $repository->shouldReceive('createLoginCode')->andThrow(new RuntimeException('database is gone'));
        app()->instance(LoginCodeRepository::class, $repository);

        $this->withoutExceptionHandling();

        expect(fn () => $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE]))
            ->toThrow(RuntimeException::class);

        expect(LoginCode::query()->sole()->consumed_at)->toBeNull();
    });

    it('answers 503 with a stable code when the sender could not deliver', function () {
        phoneUser();
        fakeLoginCodeSender(delivers: false);

        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])
            ->assertStatus(503)
            ->assertJsonPath('code', 'delivery_failed')
            ->assertJsonPath('error', 'auth.flow.login.codeDeliveryFailed');
    });

    it('returns the plain code only while the local flag is on', function () {
        phoneUser();
        fakeLoginCodeSender();
        config(['auth.login_code.expose' => true]);

        $code = $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])
            ->assertOk()
            ->json('data.dev_code');

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => $code])->assertOk();
    });

    it('never returns the plain code in production, whatever the flag says', function () {
        phoneUser();
        fakeLoginCodeSender();
        config(['auth.login_code.expose' => true]);
        app()->detectEnvironment(fn () => 'production');

        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])
            ->assertOk()
            ->assertJsonMissingPath('data.dev_code');
    });

    it('refuses a phone that is not a phone', function () {
        $this->postJson('/api/auth/phone/code', ['phone' => '12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    });

    it('stops asking after five codes a minute for one phone', function () {
        phoneUser();
        fakeLoginCodeSender();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_MASKED_PHONE])->assertOk();
        }

        // The digits and the mask are one budget.
        $this->postJson('/api/auth/phone/code', ['phone' => LOGIN_CODE_PHONE])->assertTooManyRequests();
    });
});

describe('POST /api/auth/phone/verify', function () {
    it('opens a session for the account and answers with it and its permissions', function () {
        $user = phoneUser();
        LoginCode::factory()->create();

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_MASKED_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data' => ['permissions']]);

        $this->assertAuthenticatedAs($user);
        expect(LoginCode::query()->sole()->consumed_at)->not->toBeNull();
    });

    it('does not accept the same code twice', function () {
        phoneUser();
        LoginCode::factory()->create();
        $payload = ['phone' => LOGIN_CODE_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE];

        $this->postJson('/api/auth/phone/verify', $payload)->assertOk();
        $this->postJson('/api/auth/phone/verify', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_code');
    });

    it('says a wrong code is invalid and counts the guess', function () {
        phoneUser();
        LoginCode::factory()->create();

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_code')
            ->assertJsonPath('error', 'auth.flow.login.codeInvalid');

        expect(LoginCode::query()->sole()->attempts)->toBe(1);
        $this->assertGuest();
    });

    it('burns the code on the fifth wrong guess and says so, then and afterwards', function () {
        phoneUser();
        LoginCode::factory()->create();
        $wrongGuess = ['phone' => LOGIN_CODE_PHONE, 'code' => '000000'];

        foreach (range(1, LoginCodeService::MAX_ATTEMPTS - 1) as $attempt) {
            $this->postJson('/api/auth/phone/verify', $wrongGuess)->assertJsonPath('code', 'invalid_code');
        }

        $this->postJson('/api/auth/phone/verify', $wrongGuess)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'code_locked')
            ->assertJsonPath('error', 'auth.flow.login.codeLocked');

        expect(LoginCode::query()->sole()->consumed_at)->not->toBeNull();

        // Even the right code: the screen must hear "ask for a new one", not "wrong code" forever.
        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'code_locked');
        $this->assertGuest();
    });

    it('answers a used code as invalid, not locked', function () {
        phoneUser();
        LoginCode::factory()->create();
        $payload = ['phone' => LOGIN_CODE_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE];

        $this->postJson('/api/auth/phone/verify', $payload)->assertOk();
        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => '000000'])
            ->assertJsonPath('code', 'invalid_code');
    });

    it('tells an expired code apart, so the screen can ask for a new one', function () {
        phoneUser();
        LoginCode::factory()->expired()->create();

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'code_expired');
        $this->assertGuest();
    });

    it('names the deactivation only after the right code, like the password login', function () {
        phoneUser(['status' => UserStatus::INACTIVE]);
        LoginCode::factory()->create();

        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => LoginCodeFactory::PLAIN_CODE])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'auth.accountInactive');
        $this->assertGuest();
    });

    it('refuses a code that is not six digits before looking anything up', function () {
        $this->postJson('/api/auth/phone/verify', ['phone' => LOGIN_CODE_PHONE, 'code' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    });
});

describe('the log sender', function () {
    it('is the default driver and delivers outside production', function () {
        expect(app(LoginCodeSender::class)->send(LOGIN_CODE_PHONE, '123456'))->toBeTrue();
    });

    it('refuses in production, so a missing driver fails loudly', function () {
        app()->detectEnvironment(fn () => 'production');

        expect(app(LoginCodeSender::class)->send(LOGIN_CODE_PHONE, '123456'))->toBeFalse();
    });

    it('fails when the configured driver does not exist', function () {
        config(['auth.login_code.sender' => 'carrier-pigeon']);

        app(LoginCodeSender::class);
    })->throws(InvalidArgumentException::class);
});
