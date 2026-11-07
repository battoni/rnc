<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\UserStoreDTO;
use App\Modules\Auth\DTOs\UserUpdateDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class UserService
{
    public const CANNOT_MANAGE_HIGHER_ACCOUNT = 'users.cannotManageHigherAccount';

    public function __construct(
        private PermissionService $permissionService,
        private RoleRepository $roleRepository,
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
        $users = $this->userRepository->findAllUsers($ids, $orderBy, $profileType, $perPage);
        $permissionsMap = $this->permissionService->forUsers($users->getCollection()->all());

        foreach ($users as $user) {
            $user->setAttribute('permissions', $permissionsMap[$user->id] ?? []);
        }

        return $users;
    }

    public function find(int $id): ?User
    {
        $user = $this->userRepository->findUserById($id);

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

            return $user;
        });

        $user = $this->userRepository->findUserById($user->id);
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return $user;
    }

    public function update(int $id, UserUpdateDTO $dto, ?int $actingUserId = null): ?User
    {
        $user = $this->userRepository->findUserById($id);

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

        $user = $this->userRepository->findUserById($id);

        if ($user) {
            $this->refuseIfOutranked($user, $actingUserId);
        }

        return $this->userRepository->deleteUser($id);
    }

    public function archive(int $id, ?int $actingUserId = null): ?User
    {
        $user = $this->userRepository->findUserById($id);

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

    public function isUsernameAvailable(string $username, ?int $excludeId = null): bool
    {
        return $this->userRepository->findUserByUsername($username, $excludeId) === null;
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
