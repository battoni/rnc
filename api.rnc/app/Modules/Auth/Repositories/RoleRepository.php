<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;

class RoleRepository
{
    public function __construct(
        private Role $model
    ) {}

    public function findAllRoles(): array
    {
        return $this->model->with('rolePermissions')->get()->all();
    }

    public function findRoleById(int $id): ?Role
    {
        return $this->model->with('rolePermissions')->find($id);
    }

    public function findRoleBySlug(string $slug): ?Role
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function findRoleByName(string $name): ?Role
    {
        return $this->model->with('rolePermissions')->where('name', $name)->first();
    }

    public function createRole(array $data): Role
    {
        return $this->model->create($data);
    }

    public function updateRole(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data) > 0;
    }

    /**
     * Apply only the keys that were sent, and leave the rest of the matrix alone.
     *
     * `syncRolePermissions` deletes every row and reinserts — right for a role being created, and
     * quietly destructive for one being edited: a payload with three keys erased every other
     * permission the role had, with a 200. Absence is not denial: a key set to `false` is a
     * recorded "no"; a key that never arrives is a question this request was not answering.
     *
     * @param  array<string, bool>  $permissions
     */
    public function upsertRolePermissions(int $roleId, array $permissions): void
    {
        foreach ($permissions as $permissionKey => $value) {
            RolePermission::updateOrCreate(
                ['role_id' => $roleId, 'permission_key' => $permissionKey],
                ['value' => $value],
            );
        }
    }

    /**
     * Drop only the keys named: a role that no longer holds a group has no business keeping that
     * group's rows, and everything not named survives.
     *
     * @param  array<int, string>  $permissionKeys
     */
    public function removeRolePermissions(int $roleId, array $permissionKeys): void
    {
        if (empty($permissionKeys)) {
            return;
        }

        RolePermission::where('role_id', $roleId)
            ->whereIn('permission_key', $permissionKeys)
            ->delete();
    }

    public function syncRolePermissions(int $roleId, array $permissions): void
    {
        RolePermission::where('role_id', $roleId)->delete();

        $permissionsData = [];
        foreach ($permissions as $permissionKey => $value) {
            $permissionsData[] = [
                'role_id' => $roleId,
                'permission_key' => $permissionKey,
                'value' => $value,
            ];
        }

        if (! empty($permissionsData)) {
            RolePermission::insert($permissionsData);
        }
    }

    public function getRolePermissions(int $roleId): array
    {
        return RolePermission::where('role_id', $roleId)
            ->pluck('value', 'permission_key')
            ->toArray();
    }

    public function getUsersWithRole(int $roleId): array
    {
        return User::where('role_id', $roleId)
            ->get(['name', 'last_name'])
            ->map(fn ($user) => "{$user->name} {$user->last_name}")
            ->toArray();
    }

    /**
     * @param  array<int>  $roleIds
     * @return array<int, array<int, string>> [roleId => ['Name Lastname', ...]]
     */
    public function getUsersGroupedByRoleId(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        return User::whereIn('role_id', $roleIds)
            ->get(['role_id', 'name', 'last_name'])
            ->groupBy('role_id')
            ->map(fn ($users) => $users->map(fn ($u) => "{$u->name} {$u->last_name}")->toArray())
            ->toArray();
    }

    /**
     * @param  array<int>  $roleIds
     * @return array<int, array<string, bool>> [roleId => [permissionKey => value]]
     */
    public function getRolePermissionsForRoles(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $perms = RolePermission::whereIn('role_id', $roleIds)
            ->get(['role_id', 'permission_key', 'value']);

        $result = [];
        foreach ($perms as $perm) {
            $result[$perm->role_id][$perm->permission_key] = (bool) $perm->value;
        }

        return $result;
    }

    public function deleteRole(int $id): bool
    {
        return $this->model->where('id', $id)->delete();
    }
}
