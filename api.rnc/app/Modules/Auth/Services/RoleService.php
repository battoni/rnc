<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\RolePermissionsUpdateDTO;
use App\Modules\Auth\DTOs\RoleStoreDTO;
use App\Modules\Auth\DTOs\RoleUpdateDTO;
use App\Modules\Auth\Exceptions\RoleDeletionException;
use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /** The role that holds every key. Deleting it would leave nobody able to grant anything back. */
    public const ADMINISTRATOR_SLUG = 'operador';

    /** A firm's manager (rnc-4 R14) — the role `firm:create` gives the account it makes. */
    public const MANAGER_SLUG = 'gestao';

    public const ADMINISTRATOR_KEEPS_EVERY_KEY = 'rolesAndPermissions.administratorKeepsEveryKey';

    /** The platform console's keys (rnc-4 F4a, R24): the administrator's alone, never another role's. */
    public const OPERATOR_ONLY_KEY_PREFIX = 'platform.';

    public const OPERATOR_ONLY_KEY = 'rolesAndPermissions.operatorOnlyKey';

    private const NAME_TAKEN_KEY = 'rolesAndPermissions.form.nameTaken';

    private const MAX_SLUG_ATTEMPTS = 8;

    /**
     * MySQL 8 names the index, not the column ("Duplicate entry 'x' for key 'roles.roles_name_unique'"),
     * where SQLite says "roles.name": without these a race on MySQL was a 500 (rnc-4 F7b).
     */
    private const NAME_UNIQUE_INDEX = 'roles_name_unique';

    private const SLUG_UNIQUE_INDEX = 'roles_slug_unique';

    public function __construct(
        private RoleRepository $roleRepository,
    ) {}

    /** @return Role[] */
    public function list(): array
    {
        $roles = $this->roleRepository->findAllRoles();

        $roleIds = array_map(fn (Role $r) => $r->id, $roles);
        $usersPerRole = $this->roleRepository->getUsersGroupedByRoleId($roleIds);

        foreach ($roles as $role) {
            $role->setAttribute('roleUsers', $usersPerRole[$role->id] ?? []);
        }

        return $roles;
    }

    public function find(int $id): ?Role
    {
        $role = $this->roleRepository->findRoleById($id);

        if (! $role) {
            return null;
        }

        $usersPerRole = $this->roleRepository->getUsersGroupedByRoleId([$role->id]);
        $role->setAttribute('roleUsers', $usersPerRole[$role->id] ?? []);

        return $role;
    }

    public function create(RoleStoreDTO $dto): Role
    {
        $permissionGroupsStructure = PermissionGroupsHelper::getPermissionGroupsStructure();
        $permissions = [];

        foreach ($dto->permission_group_ids as $groupId) {
            $group = collect($permissionGroupsStructure)->firstWhere('id', $groupId);
            if ($group) {
                foreach ($group['permissions'] as $permission) {
                    $permissions[$permission['key']] = false;
                }
            }
        }

        $baseSlug = Str::slug($dto->name);

        for ($attempt = 0; $attempt < self::MAX_SLUG_ATTEMPTS; $attempt++) {
            $slug = $attempt === 0 ? $baseSlug : "{$baseSlug}-{$attempt}";

            try {
                // The row and its matrix land together, or a failure leaves a role with no matrix.
                // One transaction per attempt: a slug collision rolls back only its own insert.
                $role = DB::transaction(function () use ($dto, $slug, $permissions): Role {
                    $role = $this->roleRepository->createRole([
                        'name' => $dto->name,
                        'description' => $dto->description,
                        'slug' => $slug,
                    ]);

                    $this->roleRepository->syncRolePermissions($role->id, $permissions);

                    return $role;
                });

                $role = $this->roleRepository->findRoleById($role->id);
                if (! $role) {
                    throw new ModelNotFoundException('Role not found');
                }

                $role->setAttribute('roleUsers', []);

                return $role;
            } catch (QueryException $queryException) {
                if ($this->isRolesSlugUniqueViolation($queryException)) {
                    continue;
                }

                if ($this->isRolesNameUniqueViolation($queryException)) {
                    $this->refuseTakenName();
                }

                throw $queryException;
            }
        }

        $this->refuseTakenName();
    }

    public function update(int $id, RoleUpdateDTO $dto): ?Role
    {
        // Before any write: a matrix row for a role that does not exist is a foreign-key 500.
        if (! $this->roleRepository->findRoleById($id)) {
            return null;
        }

        DB::transaction(fn () => $this->applyUpdate($id, $dto));

        return $this->find($id);
    }

    /**
     * Apply the keys the request carried, and leave the rest of the matrix as it was. The screen
     * sends the whole matrix, so nothing on it changes; a partial request stops being destructive.
     */
    public function updatePermissions(int $id, RolePermissionsUpdateDTO $dto): ?Role
    {
        $role = $this->roleRepository->findRoleById($id);

        if (! $role) {
            return null;
        }

        $permissions = $role->slug === self::ADMINISTRATOR_SLUG
            ? $this->administratorMatrix($dto->permissions)
            : $this->refuseOperatorOnlyKeys($dto->permissions);

        DB::transaction(fn () => $this->roleRepository->upsertRolePermissions($id, $permissions));

        return $this->find($id);
    }

    /**
     * A platform key granted to any role but the administrator's is refused (rnc-4 R24: roles and
     * the console stay the operator's). Revoking one — the screen sends the whole matrix, false
     * included — is harmless and passes.
     *
     * @param  array<string, bool>  $requested
     * @return array<string, bool>
     */
    private function refuseOperatorOnlyKeys(array $requested): array
    {
        foreach ($requested as $key => $granted) {
            if ($granted && str_starts_with($key, self::OPERATOR_ONLY_KEY_PREFIX)) {
                throw ValidationException::withMessages(['permissions' => [self::OPERATOR_ONLY_KEY]]);
            }
        }

        return $requested;
    }

    /**
     * The administrator holds every declared key, always — the same rule that keeps the role from
     * being deleted, applied to its matrix.
     *
     * The grant cap (`GrantableByActor`) needs a root: somebody who holds everything and can
     * therefore hand anything out. Taking a key off this role removed it from the root, and then
     * nobody — the administrator included — held it to give it back: a manager without
     * `design.read` could untick it here and lock the design surface for good. So a revoke on
     * this role is refused, for everybody.
     *
     * And every save writes the WHOLE catalogue back as granted, not only the keys that were sent.
     * That heals a matrix left incomplete — from before this rule, or by a key a new module declared
     * after the seeder ran — the next time anybody saves it.
     *
     * @param  array<string, bool>  $requested
     * @return array<string, bool>
     */
    private function administratorMatrix(array $requested): array
    {
        if (in_array(false, $requested, true)) {
            throw ValidationException::withMessages(['permissions' => [self::ADMINISTRATOR_KEEPS_EVERY_KEY]]);
        }

        $declaredKeys = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->all();

        return array_fill_keys($declaredKeys, true);
    }

    /**
     * Delete a role, and answer with it as it was. Null when there is no such role.
     *
     * Two refusals, and they are rules of the domain rather than of the HTTP layer: the
     * administrator role is never deleted, and neither is a role somebody still holds — the
     * refusal names them, so the screen can say who to move first.
     */
    public function delete(int $id): ?Role
    {
        $role = $this->find($id);

        if (! $role) {
            return null;
        }

        if ($role->slug === self::ADMINISTRATOR_SLUG) {
            throw RoleDeletionException::administrator();
        }

        $holders = $role->getAttribute('roleUsers') ?? [];

        if (! empty($holders)) {
            throw RoleDeletionException::inUse($holders);
        }

        $this->roleRepository->deleteRole($id);

        return $role;
    }

    /** The rename and the matrix change land together, or neither does. */
    private function applyUpdate(int $id, RoleUpdateDTO $dto): void
    {
        $updateData = array_filter([
            'name' => $dto->name,
            'description' => $dto->description,
        ], fn ($v) => $v !== null);

        if (! empty($updateData)) {
            try {
                $this->roleRepository->updateRole($id, $updateData);
            } catch (QueryException $queryException) {
                if ($this->isRolesNameUniqueViolation($queryException)) {
                    $this->refuseTakenName();
                }

                throw $queryException;
            }
        }

        if ($dto->permission_group_ids !== null) {
            /*
             * A group the role no longer holds loses its keys — and nothing else moves. This used
             * to delete every row and reinsert the groups named, so a payload naming fewer groups
             * than the role had (a stale form, a second admin saving a moment later) emptied the
             * rest with a 200. Upsert the keys of the named groups, delete only the keys of
             * DECLARED groups that were not named; a key belonging to no declared group is not
             * this request's business.
             */
            $permissionGroupsStructure = PermissionGroupsHelper::getPermissionGroupsStructure();
            $existingPermissions = $this->roleRepository->getRolePermissions($id);
            $grantedKeys = [];
            $revokedKeys = [];

            foreach ($permissionGroupsStructure as $group) {
                $isHeld = in_array($group['id'], $dto->permission_group_ids, true);

                foreach ($group['permissions'] as $permission) {
                    if (! $isHeld) {
                        $revokedKeys[] = $permission['key'];

                        continue;
                    }

                    $grantedKeys[$permission['key']] = $existingPermissions[$permission['key']] ?? false;
                }
            }

            $this->roleRepository->upsertRolePermissions($id, $grantedKeys);
            $this->roleRepository->removeRolePermissions($id, $revokedKeys);
        }
    }

    private function refuseTakenName(): never
    {
        throw ValidationException::withMessages([
            'name' => [self::NAME_TAKEN_KEY],
        ]);
    }

    private function isRolesSlugUniqueViolation(QueryException $queryException): bool
    {
        $lowerMessage = strtolower($queryException->getMessage());
        $mentionsSlug = str_contains($lowerMessage, 'roles.slug') || str_contains($lowerMessage, 'roles`.`slug') || str_contains($lowerMessage, self::SLUG_UNIQUE_INDEX);
        $isUniqueOrDuplicate = str_contains($lowerMessage, 'unique') || str_contains($lowerMessage, 'duplicate');

        return $mentionsSlug && $isUniqueOrDuplicate;
    }

    private function isRolesNameUniqueViolation(QueryException $queryException): bool
    {
        $lowerMessage = strtolower($queryException->getMessage());
        $mentionsName = str_contains($lowerMessage, 'roles.name') || str_contains($lowerMessage, 'roles`.`name') || str_contains($lowerMessage, self::NAME_UNIQUE_INDEX);
        $isUniqueOrDuplicate = str_contains($lowerMessage, 'unique') || str_contains($lowerMessage, 'duplicate');

        return $mentionsName && $isUniqueOrDuplicate;
    }
}
