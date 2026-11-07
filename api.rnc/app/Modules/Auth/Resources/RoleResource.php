<?php

namespace App\Modules\Auth\Resources;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissionsMap = $this->rolePermissions->pluck('value', 'permission_key')->toArray();
        $permissionGroupsStructure = PermissionGroupsHelper::getPermissionGroupsStructure();

        /*
         * EVERY declared group, granted or not. The structure says what exists; `role_permissions`
         * says what was granted, and a key with no row is simply `false`.
         *
         * This used to drop any group the role had no row for, which made a newly declared
         * permission unreachable: the screen renders only what it receives and saves only what it
         * rendered, so the missing row stayed missing. `design.read` would have been invisible on
         * every existing role but the administrator's.
         */
        $permissionGroups = array_map(fn (array $group): array => [
            'id' => $group['id'],
            'nameKey' => $group['nameKey'],
            'icon' => $group['icon'],
            // Presence no longer says whether the role has the group, so it is said: a group is held
            // when the role has a row for any of its keys. The edit form pre-selects from this.
            'isHeld' => collect($group['permissions'])->contains(
                fn (array $permission): bool => array_key_exists($permission['key'], $permissionsMap)
            ),
            'permissions' => array_map(fn (array $permission): array => [
                'labelKey' => $permission['labelKey'],
                'key' => $permission['key'],
                'value' => $permissionsMap[$permission['key']] ?? false,
            ], $group['permissions']),
        ], $permissionGroupsStructure);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'permissionGroups' => $permissionGroups,
            'users' => $this->getAttribute('roleUsers') ?? [],
        ];
    }
}
