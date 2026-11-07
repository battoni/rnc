<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
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

        $user = User::firstOrCreate(
            ['username' => 'battoni'],
            [
                'last_name' => 'Battoni',
                'name' => 'Guilherme',
                // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                'password' => config('seeding.superadmin_password'),
                'role_id' => $role->id,
                'username' => 'battoni',
            ]
        );

        $user->update(['role_id' => $role->id]);
    }
}
