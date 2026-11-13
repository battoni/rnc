<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * The operator's account (Battoni Dev) — the only account a production database is seeded with.
 * It belongs to Franco like everyone in Phase 1 (plan AM6); multi-firm access is Phase 3.
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

        $firmId = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->value('id');

        $user = User::firstOrCreate(
            ['username' => 'battoni'],
            [
                'last_name' => 'Battoni',
                'name' => 'Guilherme',
                // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                'password' => config('seeding.superadmin_password'),
                'role_id' => $role->id,
                'firm_id' => $firmId,
                'username' => 'battoni',
            ]
        );

        $user->update(['role_id' => $role->id, 'firm_id' => $firmId]);
    }
}
