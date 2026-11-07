<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * A seeded password is written in plaintext and hashed by the model.
 *
 * Found in chapaja (2026-08) and again in the overnight review (2026-09-17): `database/RULES.md`
 * says "do not pre-hash seeded passwords", and `SuperAdminSeeder` still did
 * `Hash::make(config(...))`. It only worked because the `hashed` cast recognises a hash and lets it
 * through — a second copy of the hashing rule, which breaks silently the day the cast changes.
 * The first test is the safety net: whatever the seeder writes, the column holds a hash.
 */

describe('a seeded password', function () {
    it('is stored as a hash that verifies against the configured secret', function () {
        config(['seeding.superadmin_password' => 'seeded-secret-123']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class]);

        $stored = User::where('username', 'battoni')->value('password');

        expect($stored)->not->toBe('seeded-secret-123')
            ->and(Hash::isHashed($stored))->toBeTrue()
            ->and(Hash::check('seeded-secret-123', $stored))->toBeTrue();
    });
});

arch('seeders leave hashing to the model cast')
    ->expect('Database\Seeders')
    ->not->toUse(Hash::class);
