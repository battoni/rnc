<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * The people the pilot is shown with, outside production only (R2): Reinaldo, one member of the
 * team and the four specialists a visitor books. The specialists' names are the demo's invented
 * ones (battonidev-1 `seed.js`), never real employees. Every password is `seeding.password`, the
 * shared local default — which is why this seeder refuses to run on a production database.
 */
class LocalUsersSeeder extends Seeder
{
    /** @var list<array{username: string, name: string, last_name: string, role: string, is_specialist: bool}> */
    private const PEOPLE = [
        ['username' => 'reinaldo', 'name' => 'Reinaldo', 'last_name' => 'Franco', 'role' => 'gestao', 'is_specialist' => false],
        ['username' => 'patricia', 'name' => 'Patrícia', 'last_name' => 'Gomes', 'role' => 'equipe', 'is_specialist' => false],
        ['username' => 'mariana.toledo', 'name' => 'Mariana', 'last_name' => 'Toledo', 'role' => 'equipe', 'is_specialist' => true],
        ['username' => 'carlos.prado', 'name' => 'Carlos', 'last_name' => 'Prado', 'role' => 'equipe', 'is_specialist' => true],
        ['username' => 'juliana.reis', 'name' => 'Juliana', 'last_name' => 'Reis', 'role' => 'equipe', 'is_specialist' => true],
        ['username' => 'rafael.antunes', 'name' => 'Rafael', 'last_name' => 'Antunes', 'role' => 'equipe', 'is_specialist' => true],
    ];

    private const EMAIL_DOMAIN = 'exemplo.com.br';

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firmId = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->value('id');
        $roleIds = Role::pluck('id', 'slug');

        foreach (self::PEOPLE as $person) {
            User::updateOrCreate(
                ['username' => $person['username']],
                [
                    'name' => $person['name'],
                    'last_name' => $person['last_name'],
                    'email' => $person['username'].'@'.self::EMAIL_DOMAIN,
                    'role_id' => $roleIds[$person['role']],
                    'firm_id' => $firmId,
                    'is_specialist' => $person['is_specialist'],
                    // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                    'password' => config('seeding.password'),
                ]
            );
        }
    }
}
