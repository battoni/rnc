<?php

namespace Database\Seeders;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Profile with all permissions',
            ]
        );

        $permissionGroups = PermissionGroupsHelper::getPermissionGroupsStructure();
        $permissions = [];

        foreach ($permissionGroups as $group) {
            foreach ($group['permissions'] as $permission) {
                $permissions[] = [
                    'role_id' => $role->id,
                    'permission_key' => $permission['key'],
                    'value' => true,
                ];
            }
        }

        RolePermission::where('role_id', $role->id)->delete();
        RolePermission::insert($permissions);
    }
}
