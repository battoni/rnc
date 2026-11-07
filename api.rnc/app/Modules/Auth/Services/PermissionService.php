<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Helpers\EffectivePermissionsHelper;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use Illuminate\Http\Request;

class PermissionService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private UserPermissionRepository $userPermissionRepository,
    ) {}

    /**
     * Compute effective permissions for a single user (2 queries).
     *
     * @return array<int, string>
     */
    public function forUser(User $user): array
    {
        $currentRequest = $this->getRequestForMemoization();
        $memoizationKey = $this->permissionsMemoizationKey($user->id);

        if ($currentRequest && $currentRequest->attributes->has($memoizationKey)) {
            return $currentRequest->attributes->get($memoizationKey);
        }

        $rolePermissions = $user->role_id
            ? $this->roleRepository->getRolePermissions($user->role_id)
            : [];

        $userPermissions = $this->userPermissionRepository->getUserPermissions($user->id);

        $permissions = EffectivePermissionsHelper::effectivePermissionKeys($rolePermissions, $userPermissions);

        if ($currentRequest) {
            $currentRequest->attributes->set($memoizationKey, $permissions);
        }

        return $permissions;
    }

    /**
     * Compute effective permissions for multiple users in 2 batch queries.
     *
     * @param  User[]  $users
     * @return array<int, array<int, string>> [userId => [permissionKeys]]
     */
    public function forUsers(array $users): array
    {
        if (empty($users)) {
            return [];
        }

        $userIds = array_map(fn (User $u) => $u->id, $users);
        $roleIds = array_values(array_unique(array_filter(array_map(fn (User $u) => $u->role_id, $users))));

        $rolePermissionsMap = $this->roleRepository->getRolePermissionsForRoles($roleIds);
        $userPermissionsMap = $this->userPermissionRepository->getUserPermissionsForUsers($userIds);

        $result = [];
        foreach ($users as $user) {
            $rolePerms = $rolePermissionsMap[$user->role_id] ?? [];
            $userPerms = $userPermissionsMap[$user->id] ?? [];
            $result[$user->id] = EffectivePermissionsHelper::effectivePermissionKeys($rolePerms, $userPerms);
        }

        $currentRequest = $this->getRequestForMemoization();
        if ($currentRequest) {
            foreach ($result as $userId => $permissionKeys) {
                $currentRequest->attributes->set($this->permissionsMemoizationKey($userId), $permissionKeys);
            }
        }

        return $result;
    }

    public function forgetUserMemo(int $userId): void
    {
        $currentRequest = $this->getRequestForMemoization();

        if (! $currentRequest) {
            return;
        }

        $currentRequest->attributes->remove($this->permissionsMemoizationKey($userId));
    }

    private function permissionsMemoizationKey(int $userId): string
    {
        return "effective_permissions.user.{$userId}";
    }

    private function getRequestForMemoization(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}
