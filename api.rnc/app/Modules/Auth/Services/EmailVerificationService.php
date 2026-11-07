<?php

namespace App\Modules\Auth\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use App\Modules\Auth\Models\PendingRegistration;
use App\Modules\Auth\Repositories\PendingRegistrationRepository;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    private const CODE_MAX = 999999;

    /** The fifth wrong guess burns the code, as `LoginCodeService::MAX_ATTEMPTS` does. */
    public const MAX_ATTEMPTS = 5;

    private const INVALID_CODE_KEY = 'auth.flow.signUp.verify.invalidCode';

    private const LOCKED_CODE_KEY = 'auth.flow.signUp.verify.codeLocked';

    public function __construct(
        private PendingRegistrationRepository $pendingRegistrationRepository,
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * Re-issue a code for an existing pending registration.
     *
     * Silently does nothing when no registration is pending for the address. That is not
     * laziness: answering differently would turn this endpoint into a way to ask "does
     * this person have an account here", which is the same leak the login flow has to
     * avoid. The caller always hears "sent".
     */
    public function issueCode(string $email): void
    {
        $pending = $this->pendingRegistrationRepository->findPendingRegistrationByEmail($email);

        if (! $pending) {
            return;
        }

        $this->issueCodeForPending($pending);
    }

    public function issueCodeForPending(PendingRegistration $pending): void
    {
        $code = str_pad((string) random_int(0, self::CODE_MAX), 6, '0', STR_PAD_LEFT);

        $this->pendingRegistrationRepository->updatePendingRegistrationCode(
            $pending,
            Hash::make($code),
            now()->addMinutes((int) config('registration.code_ttl_minutes')),
        );

        Mail::to($pending->email)->send(new EmailVerificationCodeMail($code));
    }

    /**
     * Check the code, promote the pending registration to a real user, delete the pending row.
     *
     * All inside a transaction with the pending row locked: a double submit would otherwise
     * let both attempts pass the checks and race on the `users.email` unique index. The
     * second one re-reads a row that is already gone and fails as an invalid code, which is
     * the truth from where it stands.
     *
     * A wrong code is RETURNED from the transaction and thrown after it: the guess has to be
     * counted, and an exception inside would roll the count back with everything else. The fifth
     * wrong guess burns the code — without a ceiling, a six-digit code falls to anyone patient
     * enough to spread the guesses over a few addresses.
     */
    public function verify(string $email, string $code): User
    {
        $outcome = DB::transaction(function () use ($email, $code): User|string {
            $pending = $this->pendingRegistrationRepository->findValidPendingRegistrationForUpdate($email);

            if (! $pending) {
                return self::INVALID_CODE_KEY;
            }

            if (! Hash::check($code, $pending->code_hash)) {
                $pending = $this->pendingRegistrationRepository->registerFailedPendingRegistrationAttempt($pending, self::MAX_ATTEMPTS);

                return $pending->attempts >= self::MAX_ATTEMPTS ? self::LOCKED_CODE_KEY : self::INVALID_CODE_KEY;
            }

            if ($this->userRepository->findUserByEmail($email)) {
                throw ValidationException::withMessages([
                    'email' => ['auth.flow.signUp.verify.userAlreadyExists'],
                ]);
            }

            $user = $this->promote($pending);

            $this->pendingRegistrationRepository->deletePendingRegistrationByEmail($email);

            return $user;
        });

        if (is_string($outcome)) {
            throw ValidationException::withMessages(['code' => [$outcome]]);
        }

        return $outcome;
    }

    /**
     * A pending registration becomes a user. Also the whole of registration when
     * `registration.verify_email` is off — there is no second path to keep in step.
     */
    public function promote(PendingRegistration $pending): User
    {
        $roleSlug = config('registration.profile_types')[$pending->profile_type] ?? null;
        $role = $roleSlug ? $this->roleRepository->findRoleBySlug($roleSlug) : null;

        $user = $this->userRepository->createUser([
            'name' => $pending->first_name,
            'last_name' => $pending->last_name,
            'username' => $pending->email,
            'email' => $pending->email,
            'phone' => $pending->phone,
            'profile_type' => $pending->profile_type,
            'cpf' => $pending->cpf,
            'birth_date' => $pending->birth_date,
            'role_id' => $role?->id,
            'status' => config('registration.new_user_status'),
            // Already hashed on the pending row; re-hashing would lock the person out.
            'password' => $pending->getRawOriginal('password'),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }
}
