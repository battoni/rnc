<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(FirmSeeder::class);
        // The platform firm (rnc-4 D3): the root host's firm, every environment.
        $this->call(PlatformFirmSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(SuperAdminSeeder::class);

        // Project-owned seeders (LOCAL) — runs in this scope, so $this->call(...) is available.
        // The cortex sync never touches seeders.local.php, so feature seeders survive updates.
        $localSeeders = __DIR__.'/seeders.local.php';

        if (file_exists($localSeeders)) {
            require $localSeeders;
        }
    }
}
