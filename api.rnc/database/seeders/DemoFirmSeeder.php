<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * Alvorada Contabilidade, the invented demo firm (rnc-4 D18) — minimal for now (F1a): the firm,
 * its manager and one specialist, enough to prove a second tenant on `alvorada.localhost`. F3b2
 * gives it the full demo. Every name, address and number is invented.
 *
 * Local only (plan AM7): demo data never enters the Pest suite's seeds nor production. Runs before
 * `SpecialistSeeder`, which gives the specialist the firm's business hours.
 */
class DemoFirmSeeder extends Seeder
{
    public const DEMO_SLUG = 'alvorada';

    /** Gestão is still the `ceo` role until F2c renames it. */
    private const MANAGER_ROLE_SLUG = 'ceo';

    private const TEAM_ROLE_SLUG = 'equipe';

    private const EMAIL_DOMAIN = 'alvorada.exemplo.com.br';

    /** @var list<array{username: string, name: string, last_name: string, role: string, is_specialist: bool}> */
    private const PEOPLE = [
        ['username' => 'helena.alvorada', 'name' => 'Helena', 'last_name' => 'Martins', 'role' => self::MANAGER_ROLE_SLUG, 'is_specialist' => false],
        ['username' => 'bruno.alvorada', 'name' => 'Bruno', 'last_name' => 'Siqueira', 'role' => self::TEAM_ROLE_SLUG, 'is_specialist' => true],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $firm = Firm::updateOrCreate(
            ['slug' => self::DEMO_SLUG],
            [
                'name' => 'Alvorada Contabilidade',
                'logo_text' => 'Alvorada Contabilidade',
                'brand_primary' => '#0f5c4d',
                'brand_accent' => '#f4b63f',
                'address' => 'Rua das Acácias, 120, Centro, Cidade Exemplo-SP',
                'phone' => '11 4000-1234',
                'mailbox' => 'contato@'.self::EMAIL_DOMAIN,
                'business_hours' => [
                    ['weekdays' => [1, 2, 3, 4, 5], 'from' => '08:30', 'to' => '17:30'],
                ],
            ]
        );

        $roleIds = Role::pluck('id', 'slug');

        foreach (self::PEOPLE as $person) {
            User::updateOrCreate(
                ['username' => $person['username']],
                [
                    'name' => $person['name'],
                    'last_name' => $person['last_name'],
                    'email' => $person['username'].'@'.self::EMAIL_DOMAIN,
                    'role_id' => $roleIds[$person['role']],
                    'firm_id' => $firm->id,
                    'is_specialist' => $person['is_specialist'],
                    // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                    'password' => config('seeding.password'),
                ]
            );
        }
    }
}
