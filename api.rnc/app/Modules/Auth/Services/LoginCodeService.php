<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Contracts\LoginCodeSender;
use App\Modules\Auth\DTOs\LoginCodeRequestDTO;
use App\Modules\Auth\DTOs\LoginCodeVerifyDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Exceptions\LoginCodeException;
use App\Modules\Auth\Models\LoginCode;
use App\Modules\Auth\Repositories\LoginCodeRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Services\FirmResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in by a one-time code sent to the account's phone — no password.
 *
 * Only the hash of a code is stored, a new code supersedes every open one for the phone, and the
 * fifth wrong guess burns the code. The code signs in to an EXISTING account; creating one on the
 * first verify is a product decision (who is this person, which role do they get) and belongs in
 * the product, not here.
 */
class LoginCodeService
{
    public const CODE_LENGTH = 6;

    public const CODE_TTL_MINUTES = 10;

    /** The fifth wrong guess burns the code — the person has to ask for a new one. */
    public const MAX_ATTEMPTS = 5;

    private const SECONDS_PER_MINUTE = 60;

    private const VISIBLE_PHONE_DIGITS = 4;

    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmResolver $firmResolver,
        private LoginCodeRepository $loginCodeRepository,
        private LoginCodeSender $loginCodeSender,
        private PermissionService $permissionService,
        private UserRepository $userRepository,
    ) {}

    /**
     * Issue a fresh code and hand it to the sender.
     *
     * A phone with no account gets the same answer and the same stored row — only nothing is
     * sent. Skipping the row would leave that phone forever at `invalid_code`, while a real one
     * moves on to `code_expired` and `code_locked`: waiting ten minutes, or guessing five times,
     * would tell a stranger which numbers have accounts.
     *
     * @return array{phone: string, expires_in: int, dev_code: string|null}
     */
    public function request(LoginCodeRequestDTO $dto): array
    {
        $code = $this->generateCode();
        $hasAccount = $this->findAccount($dto->phone) !== null;

        $this->issue($dto->phone, $code, deliver: $hasAccount);

        return [
            'phone' => $dto->phone,
            'expires_in' => self::CODE_TTL_MINUTES * self::SECONDS_PER_MINUTE,
            'dev_code' => $hasAccount && $this->exposesCode() ? $code : null,
        ];
    }

    /**
     * Spend the code and open the session. Throws `LoginCodeException` (`invalid_code` |
     * `code_expired` | `code_locked`), or the same `auth.accountInactive` refusal the password login gives.
     */
    public function verify(LoginCodeVerifyDTO $dto, Request $request): User
    {
        // The failure is RETURNED from the transaction and thrown after it: a wrong guess has to
        // be counted, and an exception inside would roll the count back with everything else.
        $failure = DB::transaction(fn (): ?LoginCodeException => $this->spend($dto));

        if ($failure) {
            throw $failure;
        }

        $user = $this->findAccount($dto->phone);

        if (! $user) {
            throw LoginCodeException::invalid();
        }

        if ($user->status === UserStatus::INACTIVE) {
            throw ValidationException::withMessages(['phone' => ['auth.accountInactive']]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    /**
     * The account behind the phone on THIS firm's host (rnc-4 R4, AM2). Another firm's account is
     * no account here: no code is sent for it and spending one answers like a wrong code.
     */
    private function findAccount(string $phone): ?User
    {
        $firm = $this->currentFirm->get();
        $user = $this->userRepository->findSoleUserByPhone($phone, $firm);

        return $user !== null && $this->firmResolver->belongsTo($user, $firm) ? $user : null;
    }

    private function issue(string $phone, string $code, bool $deliver): void
    {
        // One transaction: if the new row fails, the codes it was about to supersede stay usable.
        // The delivery stays outside it — a provider call must not hold the rows locked.
        DB::transaction(function () use ($phone, $code): void {
            $this->loginCodeRepository->consumeOpenLoginCodesForPhone($phone);
            $this->loginCodeRepository->createLoginCode([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'attempts' => 0,
                'consumed_at' => null,
            ]);
        });

        if (! $deliver || $this->loginCodeSender->send($phone, $code)) {
            return;
        }

        Log::warning('auth.login_code.delivery_failed', ['phone_suffix' => substr($phone, -self::VISIBLE_PHONE_DIGITS)]);

        throw LoginCodeException::deliveryFailed();
    }

    private function spend(LoginCodeVerifyDTO $dto): ?LoginCodeException
    {
        $loginCode = $this->loginCodeRepository->findLatestUnconsumedLoginCodeByPhoneForUpdate($dto->phone);

        // Nothing open: either there never was a code, or the last one was burned. The second has
        // to be said, or the person keeps typing into a code that can no longer work.
        if (! $loginCode) {
            return $this->loginCodeRepository->wasLatestLoginCodeBurned($dto->phone, self::MAX_ATTEMPTS)
                ? LoginCodeException::locked()
                : LoginCodeException::invalid();
        }

        if ($loginCode->isExpired()) {
            return LoginCodeException::expired();
        }

        if (! Hash::check($dto->code, $loginCode->code_hash)) {
            return $this->registerFailedAttempt($loginCode)
                ? LoginCodeException::locked()
                : LoginCodeException::invalid();
        }

        $this->loginCodeRepository->updateLoginCode($loginCode, ['consumed_at' => now()]);

        return null;
    }

    /** Counts the wrong guess and answers whether it was the one that burned the code. */
    private function registerFailedAttempt(LoginCode $loginCode): bool
    {
        $attempts = $loginCode->attempts + 1;
        $data = ['attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $data['consumed_at'] = now();
        }

        $this->loginCodeRepository->updateLoginCode($loginCode, $data);

        return $attempts >= self::MAX_ATTEMPTS;
    }

    private function generateCode(): string
    {
        $maximum = (10 ** self::CODE_LENGTH) - 1;

        return str_pad((string) random_int(0, $maximum), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /** Never in production, whatever the environment says — the flag is a local convenience. */
    private function exposesCode(): bool
    {
        return (bool) config('auth.login_code.expose') && ! app()->isProduction();
    }
}
