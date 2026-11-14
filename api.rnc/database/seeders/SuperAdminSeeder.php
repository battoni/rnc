<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Database\Seeder;

/**
 * The operator's account (Battoni Dev) — the only account a production database is seeded with.
 * It belongs to no firm (rnc-4 F4a, R24): it signs in at the root only, works in the platform firm
 * and reaches the other firms through the console. A database seeded before keeps the account and
 * loses its firm on the next seed.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => RoleService::ADMINISTRATOR_SLUG],
            [
                'name' => 'Operador',
                'description' => 'Battoni Dev: opera o produto, todas as permissões',
            ]
        );

        $user = User::firstOrCreate(
            ['username' => 'battoni'],
            [
                'last_name' => 'Battoni',
                'name' => 'Guilherme',
                // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                'password' => config('seeding.superadmin_password'),
                'role_id' => $role->id,
                'firm_id' => null,
                'username' => 'battoni',
            ]
        );

        $user->update(['role_id' => $role->id, 'firm_id' => null]);
    }
}
