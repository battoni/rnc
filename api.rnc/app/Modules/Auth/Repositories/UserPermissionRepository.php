<?php

namespace App\Modules\Auth\Repositories;

use App\Modules\Auth\Models\UserPermission;
use Illuminate\Support\Collection;

class UserPermissionRepository
{
    public function __construct(
        private UserPermission $model
    ) {}

    public function getUserPermissions(int $userId): array
    {
        return $this->model->where('user_id', $userId)
            ->pluck('value', 'permission_key')
            ->toArray();
    }

    /**
     * @param  array<int>  $userIds
     * @return array<int, array<string, bool>> [userId => [permissionKey => value]]
     */
    public function getUserPermissionsForUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $result = [];
        $this->model->whereIn('user_id', $userIds)
            ->get(['user_id', 'permission_key', 'value'])
            ->each(function ($perm) use (&$result) {
                $result[$perm->user_id][$perm->permission_key] = (bool) $perm->value;
            });

        return $result;
    }

    public function syncUserPermissions(int $userId, array $permissions): void
    {
        UserPermission::where('user_id', $userId)->delete();

        $permissionsData = [];
        foreach ($permissions as $permissionKey => $value) {
            $permissionsData[] = [
                'user_id' => $userId,
                'permission_key' => $permissionKey,
                'value' => $value,
            ];
        }

        if (! empty($permissionsData)) {
            UserPermission::insert($permissionsData);
        }
    }

    /**
     * @return Collection<int, UserPermission>
     */
    public function findUserPermissionRowsByUserId(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)->get();
    }

    /**
     * @param  array<int>  $ids
     */
    public function deleteUserPermissionsByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $this->model->whereIn('id', $ids)->delete();
    }
}
