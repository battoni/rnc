<?php

namespace App\Modules\Auth\Helpers;

class PermissionGroupsHelper
{
    public static function getPermissionGroupsStructure(): array
    {
        $groups = [
            [
                'id' => 'users',
                'nameKey' => 'rolesAndPermissions.groups.users',
                'icon' => 'pi pi-users',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'users.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.search', 'key' => 'users.search', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.create', 'key' => 'users.create', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.update', 'key' => 'users.update', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.resetPassword', 'key' => 'users.reset_password', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.archive', 'key' => 'users.archive', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.delete', 'key' => 'users.delete', 'value' => false],
                ],
            ],
            [
                'id' => 'home',
                'nameKey' => 'rolesAndPermissions.groups.home',
                'icon' => 'pi pi-home',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'home.read', 'value' => false],
                ],
            ],
            [
                'id' => 'permissions',
                'nameKey' => 'rolesAndPermissions.groups.permissions',
                'icon' => 'pi pi-key',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'permissions.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.update', 'key' => 'permissions.update', 'value' => false],
                ],
            ],
        ];

        // Project-owned permission groups (LOCAL) — appended; the cortex sync never touches
        // permission-groups.local.php, so feature permissions survive every rnc update.
        $localGroups = __DIR__.'/permission-groups.local.php';

        if (file_exists($localGroups)) {
            $groups = array_merge($groups, require $localGroups);
        }

        return $groups;
    }
}
