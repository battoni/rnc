<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionGroups = PermissionGroupsHelper::getPermissionGroupsStructure();

        $role = Role::firstOrCreate(
            ['slug' => 'manager'],
            [
                'name' => 'Manager',
                'description' => 'Every feature except the design gallery',
            ]
        );

        $userRole = Role::firstOrCreate(
            ['slug' => 'user'],
            [
                'name' => 'User',
                'description' => 'No permissions profile',
            ]
        );

        /*
         * What the ordinary member role does NOT get. Three families, for three reasons:
         * administering other people's accounts, moderating what other people wrote, and
         * deciding who may do what — a member holding `permissions.update` can grant
         * themselves every key on this list, which would make the list decoration.
         * The filter used to be scoped to the `users` group; it is keyed now, so a new group
         * can withhold something without the loop learning about it.
         *
         * A project denies the member its own keys (write access to a LOCAL group, say) in
         * `Auth/Helpers/user-exclusions.local.php` (LOCAL), never here: this file is synced.
         */
        $excludedUserPermissionKeys = [
            /*
             * The member does not read the staff directory. `users.read` returned every account's
             * name, e-mail and phone to anybody who signed themselves up, and it was ALSO the
             * signal the documentation portal used for "this person is staff" — so a member walked
             * into the internal docs (ARC-15, LP-DOCS-01). `users.reset_password` goes with it: a
             * member has no account but their own to recover.
             */
            'users.read',
            'users.reset_password',
            /*
             * Nor the tips' back office. `tips.read` opens the Dicas management screen — every tip,
             * archived ones included, and each one's history: earlier texts, the name of the staff
             * member who changed it and the reason they wrote down. A member needs none of it: the
             * tips themselves render through `tips/active`, which asks for no key (decided
             * 2026-09-22).
             */
            'tips.read',
            'users.search',
            'users.create',
            'users.update',
            'users.archive',
            'users.delete',
            'permissions.read',
            'permissions.update',
            'ideas.manage',
            'news.manage',
            'tips.manage',
            'design.read',
            ...PermissionGroupsHelper::getLocalUserExclusions(),
        ];

        /*
         * What the manager does NOT get, although it gets every other group. `design.read` used to
         * be a role check on the administrator's slug, so only the administrator could open those
         * pages; moving the gate to a permission must not quietly hand them to anyone else. The
         * owner can still grant it from the permissions tab — which is the point of the move.
         *
         * A project adds its own keys in `Auth/Helpers/manager-exclusions.local.php` (LOCAL), never
         * here: this file is synced, and an edit to the list would be lost on the next update.
         */
        $excludedManagerPermissionKeys = [
            'design.read',
            ...PermissionGroupsHelper::getLocalManagerExclusions(),
        ];

        $managerPermissions = [];
        $userPermissions = [];

        foreach ($permissionGroups as $group) {
            $managerGroupPermissions = array_values(array_filter(
                $group['permissions'],
                fn (array $permission) => ! in_array($permission['key'], $excludedManagerPermissionKeys, true),
            ));
            $userGroupPermissions = $group['permissions'];

            $userGroupPermissions = array_values(array_filter(
                $userGroupPermissions,
                fn (array $permission) => ! in_array($permission['key'], $excludedUserPermissionKeys, true),
            ));

            foreach ($managerGroupPermissions as $permission) {
                $managerPermissions[] = [
                    'role_id' => $role->id,
                    'permission_key' => $permission['key'],
                    'value' => true,
                ];
            }

            foreach ($userGroupPermissions as $permission) {
                $userPermissions[] = [
                    'role_id' => $userRole->id,
                    'permission_key' => $permission['key'],
                    'value' => true,
                ];
            }
        }

        RolePermission::where('role_id', $role->id)->delete();
        if (! empty($managerPermissions)) {
            RolePermission::insert($managerPermissions);
        }

        RolePermission::where('role_id', $userRole->id)->delete();
        if (! empty($userPermissions)) {
            RolePermission::insert($userPermissions);
        }

        User::firstOrCreate(
            ['username' => 'manager'],
            [
                'name' => 'Manager',
                'last_name' => 'Demo',
                'username' => 'manager',
                'role_id' => $role->id,
                'password' => config('seeding.password'),
            ]
        );

        User::firstOrCreate(
            ['username' => 'user_without_permissions'],
            [
                'name' => 'User',
                'last_name' => 'No Profile',
                'username' => 'user_without_permissions',
                'role_id' => $userRole->id,
                'password' => config('seeding.password'),
            ]
        );
    }
}
