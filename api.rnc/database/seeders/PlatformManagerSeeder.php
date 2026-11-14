<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * The platform firm's manager (rnc-4 R23): a `gestao` login on `hub`, so the root host's pipeline —
 * the leads of a diagnostic taken on the platform's own site — has somebody to work it locally.
 *
 * Local only, like the demo firm (plan AM7): the password is the shared `seeding.password`, and the
 * Pest suite's seed stays exactly as `SeedShapeTest` states it. After `RoleSeeder` and
 * `PlatformFirmSeeder`.
 */
class PlatformManagerSeeder extends Seeder
{
    public const USERNAME = 'gestao.hub';

    private const EMAIL = 'gestao@hub.exemplo.com.br';

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $firmId = Firm::where('slug', PlatformFirmSeeder::PLATFORM_SLUG)->value('id');
        $roleId = Role::where('slug', DemoFirmSeeder::MANAGER_ROLE_SLUG)->value('id');

        if ($firmId === null || $roleId === null) {
            return;
        }

        User::updateOrCreate(
            ['username' => self::USERNAME],
            [
                'name' => 'Gestão',
                'last_name' => 'Plataforma',
                'email' => self::EMAIL,
                'role_id' => $roleId,
                'firm_id' => $firmId,
                'is_specialist' => false,
                // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                'password' => config('seeding.password'),
            ]
        );
    }
}
