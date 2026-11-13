<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\LinkedAccountDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The accounts another module provisions for its own records (rnc-2 R1–R2): today a partner's
 * portal login. Auth owns `users` and `sessions`, so the writes happen here (arcus "a module reads
 * another module's tables and never writes them"); the Partner module decides when.
 *
 * The password is generated, handed back once to be shown once, and stored only as the `hashed`
 * cast's hash. Changing it or closing the account deletes the stored sessions, so a cookie handed
 * out before is worth nothing on its next request (plan AM5).
 */
class LinkedAccountService
{
    public const PASSWORD_LENGTH = 16;

    public function __construct(
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
    ) {}

    /** The partner's login, active or closed — null when it never had one. */
    public function findPartnerAccount(int $partnerId): ?User
    {
        return $this->userRepository->findUserByPartnerId($partnerId);
    }

    /**
     * Open the account, or reopen the closed one the record already has (one row per partner, so a
     * regrant keeps the history pointing at the same user).
     *
     * @return array{user: User, password: string}
     */
    public function openAccount(LinkedAccountDTO $account, ?User $closed = null): array
    {
        $role = $this->roleRepository->findRoleBySlug($account->roleSlug);

        if (! $role) {
            throw new RuntimeException("The role [{$account->roleSlug}] is not seeded.");
        }

        $password = $this->newPassword();
        $attributes = [
            'name' => $account->name,
            'last_name' => '',
            'username' => $account->username,
            'email' => $account->email,
            'role_id' => $role->id,
            'firm_id' => $account->firmId,
            'partner_id' => $account->partnerId,
            'status' => UserStatus::ACTIVE,
            'password' => $password,
        ];

        $user = $closed
            ? $this->userRepository->saveUser($closed, $attributes)
            : $this->userRepository->createUser($attributes);

        return ['user' => $user, 'password' => $password];
    }

    /** A new password, returned once; every session opened with the old one ends. */
    public function resetPassword(User $user): string
    {
        $password = $this->newPassword();

        $this->userRepository->saveUser($user, ['password' => $password]);
        $this->userRepository->deleteUserSessions($user->id);

        return $password;
    }

    /** Deactivated and signed out everywhere: the next login names `auth.accountInactive`. */
    public function closeAccount(User $user): User
    {
        $this->userRepository->saveUser($user, ['status' => UserStatus::INACTIVE]);
        $this->userRepository->deleteUserSessions($user->id);

        return $user;
    }

    private function newPassword(): string
    {
        return Str::password(self::PASSWORD_LENGTH, symbols: false);
    }
}
