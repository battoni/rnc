<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\UserStoreDTO;
use App\Modules\Auth\DTOs\UserUpdateDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Booking\Services\SpecialistService;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class UserService
{
    /** What an offboarded firm's login is renamed to (verify P1-A2). */
    public const ANONYMISED_USERNAME_PREFIX = 'desligado.';

    private const ANONYMISED_NAME = 'Pessoa';

    private const ANONYMISED_LAST_NAME = 'removida';

    private const ANONYMISED_PASSWORD_LENGTH = 64;

    public const CANNOT_MANAGE_HIGHER_ACCOUNT = 'users.cannotManageHigherAccount';

    public function __construct(
        private CurrentFirm $currentFirm,
        private PermissionService $permissionService,
        private RoleRepository $roleRepository,
        private SpecialistService $specialistService,
        private UserPermissionRepository $userPermissionRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * @param  array<int>|null  $ids  Filter by user ids.
     * @param  string|null  $orderBy  One of: name_asc, name_desc, status_asc, status_desc, role_asc, role_desc.
     * @param  string|null  $profileType  Filter by registration profile type (a key of `registration.profile_types`).
     * @return LengthAwarePaginator<int, User>
     */
    public function list(?array $ids = null, ?string $orderBy = null, ?string $profileType = null, int $perPage = 15): LengthAwarePaginator
    {
        $users = $this->userRepository->findAllUsers($this->currentFirm->id(), $ids, $orderBy, $profileType, $perPage);
        $permissionsMap = $this->permissionService->forUsers($users->getCollection()->all());

        foreach ($users as $user) {
            $user->setAttribute('permissions', $permissionsMap[$user->id] ?? []);
        }

        return $users;
    }

    public function find(int $id): ?User
    {
        $user = $this->userRepository->findFirmUserById($id, $this->currentFirm->id());

        if ($user) {
            $user->setAttribute('permissions', $this->permissionService->forUser($user));
        }

        return $user;
    }

    public function create(UserStoreDTO $dto): User
    {
        // The account and its overrides land together: a failure between them left an account
        // without the permissions the form granted, and a 500 for a row that was already written.
        $user = DB::transaction(function () use ($dto): User {
            $user = $this->userRepository->createUser([
                'name' => $dto->name,
                'last_name' => $dto->last_name,
                'username' => $dto->username,
                'email' => $dto->email,
                'is_specialist' => $dto->is_specialist,
                // Made inside the firm of whoever makes it: a user the firm could not list is a lost one.
                'firm_id' => $this->currentFirm->id(),
                'role_id' => $dto->role_id,
                'status' => $dto->status,
                'password' => Hash::make($dto->password),
            ]);

            if (! empty($dto->permissions)) {
                $this->userPermissionRepository->syncUserPermissions(
                    $user->id,
                    $this->onlyWhatDivergesFromRole($dto->role_id, $dto->permissions)
                );
            }

            if ($dto->is_specialist) {
                $this->startSpecialist($user);
            }

            return $user;
        });

        $user = $this->userRepository->findUserById($user->id);
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    public function update(int $id, UserUpdateDTO $dto, ?int $actingUserId = null): ?User
    {
        $user = $this->userRepository->findFirmUserById($id, $this->currentFirm->id());

        if (! $user) {
            return null;
        }

        $this->refuseIfOutranked($user, $actingUserId);

        $isDeactivatingSelf = $actingUserId === $id && $dto->status === UserStatus::INACTIVE->value;

        if ($isDeactivatingSelf) {
            $this->refuseSelfDeactivation();
        }

        $data = [];

        $updateCandidates = [
            'name' => $dto->name,
            'last_name' => $dto->last_name,
            'username' => $dto->username,
            'role_id' => $dto->role_id,
            'status' => $dto->status,
            'password' => $dto->password,
            'is_specialist' => $dto->is_specialist,
        ];

        foreach ($updateCandidates as $field => $value) {
            if ($value instanceof Optional) {
                continue;
            }

            if ($field === 'password') {
                if ($value === null) {
                    continue;
                }

                $data['password'] = Hash::make($value);

                continue;
            }

            $data[$field] = $value;
        }

        // The edit and the overrides land together: `syncUserPermissions` deletes before it
        // inserts, so a failure between them used to leave the person with no overrides at all.
        DB::transaction(function () use ($id, $data, $dto, $user): void {
            if (! empty($data)) {
                $this->userRepository->updateUser($id, $data);
            }

            if (($data['is_specialist'] ?? false) === true) {
                $this->startSpecialist($user);
            }

            if ($dto->permissions instanceof Optional) {
                return;
            }

            // Measured against the role the user will have AFTER this request: a request that moves
            // someone to a new profile and sends the matrix in the same breath is about where they go.
            // `array_key_exists`, not `??`: an explicit null role is a change too, and `$user` is the row as
            // it was before the update.
            $roleId = array_key_exists('role_id', $data) ? $data['role_id'] : $user->role_id;

            $this->userPermissionRepository->syncUserPermissions(
                $id,
                $this->onlyWhatDivergesFromRole($roleId, $dto->permissions)
            );
        });

        // A password another account replaced ends every session the person had, as `resetPassword`
        // does (rnc-4 verify PE3): whoever held the old one is signed out. Your own edit keeps yours.
        if (array_key_exists('password', $data) && $id !== $actingUserId) {
            $this->userRepository->deleteUserSessions($id);
        }

        $this->permissionService->forgetUserMemo($id);

        $user = $this->userRepository->findUserById($id);
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    /**
     * Deleting yourself is one click from locking the product's only administrator out of it, with
     * nobody left who could let them back in. The API refuses rather than trusting the interface to
     * hide the button — the same reasoning as refusing self-deactivation.
     */
    public function delete(int $id, ?int $actingUserId = null): bool
    {
        if ($actingUserId === $id) {
            throw ValidationException::withMessages([
                'id' => ['users.cannotDeleteSelf'],
            ]);
        }

        // Another firm's account reads as none, and nothing is deleted (rnc-4 AM2).
        $user = $this->userRepository->findFirmUserById($id, $this->currentFirm->id());

        if (! $user) {
            return false;
        }

        $this->refuseIfOutranked($user, $actingUserId);

        return $this->userRepository->deleteUser($id);
    }

    public function archive(int $id, ?int $actingUserId = null): ?User
    {
        $user = $this->userRepository->findFirmUserById($id, $this->currentFirm->id());

        if (! $user) {
            return null;
        }

        $this->refuseIfOutranked($user, $actingUserId);

        // Toggling an ACTIVE self can only mean deactivating it. Re-activating is unreachable: an
        // inactive account is signed out on its next request and never gets here.
        $isDeactivatingSelf = $actingUserId === $id && $user->status === UserStatus::ACTIVE;

        if ($isDeactivatingSelf) {
            $this->refuseSelfDeactivation();
        }

        $newStatus = $user->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;
        $this->userRepository->updateUser($id, ['status' => $newStatus->value]);

        $this->permissionService->forgetUserMemo($id);

        $user = $this->userRepository->findUserById($id);
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    /**
     * A new password for an account, and every session it had ends (rnc-4 F4a: the console's manager
     * reset). The caller decides whose — this writes the account it is handed.
     */
    public function resetPassword(User $user, string $password): User
    {
        // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
        $user = $this->userRepository->saveUser($user, ['password' => $password]);
        $this->userRepository->deleteUserSessions($user->id);

        return $user;
    }

    /**
     * Every login of a firm leaving the platform loses what identifies a person (rnc-4 R40, verify
     * P1-A2; `FirmOffboarder`): name, e-mail, phone, CPF and birth date go, the username becomes a
     * tombstone (`desligado.<id>`) so the name is free again, the password is replaced by a random one
     * nobody holds, the account is deactivated and every session it had ends. The rows stay, so what
     * points at them (timeline authors, audit rows) still resolves. Returns how many were anonymised.
     */
    public function anonymiseFirmUsers(int $firmId): int
    {
        $users = $this->userRepository->findAllFirmUsers($firmId);

        foreach ($users as $user) {
            // Plaintext on purpose: the `hashed` cast hashes the random password on save.
            $this->userRepository->saveUser($user, [
                'name' => self::ANONYMISED_NAME,
                'last_name' => self::ANONYMISED_LAST_NAME,
                'username' => self::ANONYMISED_USERNAME_PREFIX.$user->id,
                'email' => null,
                'phone' => null,
                'cpf' => null,
                'birth_date' => null,
                'status' => UserStatus::INACTIVE->value,
                'password' => Str::random(self::ANONYMISED_PASSWORD_LENGTH),
            ]);
            $this->userRepository->deleteUserSessions($user->id);
        }

        return count($users);
    }

    /** The account with this username, whichever firm it is in; null when there is none. */
    public function findByUsername(string $username): ?User
    {
        return $this->userRepository->findUserByUsername($username);
    }

    public function isUsernameAvailable(string $username, ?int $excludeId = null): bool
    {
        return $this->userRepository->findUserByUsername($username, $excludeId) === null;
    }

    /** E-mails are unique across every firm, like usernames (rnc-4 AM2). */
    public function isEmailAvailable(string $email): bool
    {
        return $this->userRepository->findUserByEmail($email) === null;
    }

    /**
     * Keep only the answers that DISAGREE with the profile.
     *
     * The permissions screen sends the whole matrix — every key, true or false — and storing all of
     * it made a per-person copy of the profile, frozen on the day the account was made. From then on
     * editing the profile reached nobody. A row now means "this person deliberately differs": agreed
     * with the profile is silence, which is what lets the profile keep deciding; a grant it does not
     * give and a denial of something it does are both kept.
     *
     * @param  array<string, bool>  $permissions
     * @return array<string, bool>
     */
    private function onlyWhatDivergesFromRole(?int $roleId, array $permissions): array
    {
        $rolePermissions = $roleId ? $this->roleRepository->getRolePermissions($roleId) : [];

        return array_filter(
            $permissions,
            fn (bool $value, string $key) => $value !== (bool) ($rolePermissions[$key] ?? false),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * A person just flagged a specialist is bookable on the firm's business hours (rnc-4 R32); one
     * who was a specialist before keeps its own hours (`SpecialistService::startOnFirmHours`). The
     * row lands in the person's own firm — the one this request runs as, which is the person's.
     */
    private function startSpecialist(User $user): void
    {
        $firm = $this->currentFirm->get();

        if (! $firm) {
            return;
        }

        $this->specialistService->startOnFirmHours($user, $firm);
    }

    /**
     * An administrator who deactivates their own account is signed out by `EnsureAccountActive`
     * on the very next request, and cannot sign back in to undo it. If they were the only
     * administrator, nobody can.
     */
    /**
     * Nobody edits, archives or deletes an account that holds more than they do.
     *
     * The grant cap stops a person from handing out a key they lack, but not from taking power away
     * from someone above them: a manager without `design.read` could put a denying override on the
     * administrator's own row (a personal override beats the role), move them to a smaller profile,
     * or deactivate them — and then nobody held the key to put it back. "More" means the target
     * holds at least one key the actor does not; equal or less is fair game, and your own row is
     * governed by the self rules above, not by this one.
     *
     * Null actor is an internal call (a seeder, a job) and is not refused here.
     */
    private function refuseIfOutranked(User $target, ?int $actingUserId): void
    {
        if ($actingUserId === null || $actingUserId === $target->id) {
            return;
        }

        $actor = $this->userRepository->findUserById($actingUserId);
        $actorKeys = $actor ? $this->permissionService->forUser($actor) : [];

        if (array_diff($this->permissionService->forUser($target), $actorKeys) === []) {
            return;
        }

        throw ValidationException::withMessages([
            'id' => [self::CANNOT_MANAGE_HIGHER_ACCOUNT],
        ]);
    }

    private function refuseSelfDeactivation(): never
    {
        throw ValidationException::withMessages([
            'status' => ['users.form.cannotDeactivateSelf'],
        ]);
    }
}
