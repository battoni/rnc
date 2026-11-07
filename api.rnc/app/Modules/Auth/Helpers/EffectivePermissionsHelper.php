<?php

namespace App\Modules\Auth\Helpers;

class EffectivePermissionsHelper
{
    /**
     * Merge role permissions with user permission overrides (user wins).
     * Return array of permission keys that are true.
     *
     * @param  array<string, bool>  $rolePermissions
     * @param  array<string, bool>  $userPermissions
     * @return array<int, string>
     */
    public static function effectivePermissionKeys(array $rolePermissions, array $userPermissions): array
    {
        $merged = array_merge($rolePermissions, $userPermissions);

        return array_keys(array_filter($merged, fn (bool $value) => $value));
    }
}
