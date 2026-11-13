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
