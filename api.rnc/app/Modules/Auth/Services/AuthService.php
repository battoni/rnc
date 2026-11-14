<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\LoginCredentialsDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Services\FirmResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * A real bcrypt hash of a value nobody knows, used only to spend the same time verifying a
     * password when there is no account to verify it against. It must be a genuine hash at the
     * project's cost factor — a literal like 'x' returns immediately and equalises nothing.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$GCmU2q/rJo53C0yv7Zmb9Of/a5rWzqp0/cEdeIuYrzPzIWgwyeBJe';

    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmResolver $firmResolver,
        private PermissionService $permissionService,
        private UserRepository $userRepository,
    ) {}

    public function authenticate(LoginCredentialsDTO $credentials, Request $request): User
    {
        $user = $this->userRepository->findUserByUsername($credentials->username);

        // A hash to check against when there is no user, so the timing of the two paths matches.
        // Without it `Hash::check` never ran for an unknown username and the answer came back in
        // a fraction of the time — the response said "no account here" without saying it.
        $passwordHash = $user !== null ? $user->password : self::TIMING_EQUALISER_HASH;
        $isPasswordValid = Hash::check($credentials->password, $passwordHash);

        // `status` is cast to the enum on the model, so this compares cases and never strings.
        $isDeactivated = $user?->status === UserStatus::INACTIVE;

        // Unknown username and wrong password share one sentence (and neither field is named), so
        // a stranger cannot learn which addresses exist by reading which refusal comes back.
        // A deactivated account used to share that sentence too — "your account is disabled"
        // without a password proved the address was real. With a correct password the caller
        // already knows the account; telling them it is deactivated and to speak to an
        // administrator is the useful answer, not an enumeration leak.
        // An account of another firm is the same refusal, checked after the hash so the timing does
        // not move (rnc-4 R4, D5): "this person exists, on another host" is an enumeration too.
        if (! $user || ! $isPasswordValid || ! $this->firmResolver->belongsTo($user, $this->currentFirm->get())) {
            throw ValidationException::withMessages([
                'username' => ['auth.login.invalidCredentials'],
            ]);
        }

        if ($isDeactivated) {
            throw ValidationException::withMessages([
                'username' => ['auth.accountInactive'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    /**
     * @return array<int, string>
     */
    public function getSupportNames(): array
    {
        $activeUsers = $this->userRepository->findAllActiveUsers($this->currentFirm->id());
        if (empty($activeUsers)) {
            return [];
        }

        $permissionsByUserId = $this->permissionService->forUsers($activeUsers);
        $supportNames = [];

        foreach ($activeUsers as $activeUser) {
            $permissions = $permissionsByUserId[$activeUser->id] ?? [];
            $hasResetPasswordPermission = in_array('users.reset_password', $permissions, true);
            if (! $hasResetPasswordPermission) {
                continue;
            }

            $supportNames[] = trim("{$activeUser->name} {$activeUser->last_name}");
        }

        return array_values(array_filter($supportNames));
    }
}
