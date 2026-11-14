<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Content\Services\SectorPageService;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Database\Seeder;

/**
 * Alvorada Contabilidade, the invented demo firm (rnc-4 D18, R22) that a prospect is shown: the firm,
 * its six sector pages and consent v1, its manager, one team member and two specialists. Its data —
 * leads, meetings, partners and their logins, traffic, portfolio, contracts, NF-e, assistant notes —
 * comes from the same seeders as Franco's, which run once per seeded firm (`SeedsEachDemoFirm`,
 * F3b2). Every name, address and number is invented.
 *
 * Local only (plan AM7): demo data never enters the Pest suite's seeds nor production — and with no
 * Alvorada row, the per-firm seeders seed Franco alone. Runs before `SpecialistSeeder`, which gives
 * the specialists the firm's business hours.
 */
class DemoFirmSeeder extends Seeder
{
    public const DEMO_SLUG = 'alvorada';

    /** The manager is the `gestao` role (rnc-4 F2c1, R14). */
    public const MANAGER_ROLE_SLUG = 'gestao';

    private const TEAM_ROLE_SLUG = 'equipe';

    private const EMAIL_DOMAIN = 'alvorada.exemplo.com.br';

    /** @var list<array{value: string, label: string}> invented, like everything here (rnc-4 F2a) */
    private const PROOF = [
        ['value' => '18 anos', 'label' => 'em Cidade Exemplo'],
        ['value' => '300+', 'label' => 'empresas atendidas'],
        ['value' => 'Simples', 'label' => 'e Lucro Presumido'],
        ['value' => 'Online', 'label' => 'rotina sem papel'],
    ];

    /** @var list<array{username: string, name: string, last_name: string, role: string, is_specialist: bool}> */
    private const PEOPLE = [
        ['username' => 'helena.alvorada', 'name' => 'Helena', 'last_name' => 'Martins', 'role' => self::MANAGER_ROLE_SLUG, 'is_specialist' => false],
        ['username' => 'equipe.alvorada', 'name' => 'Lívia', 'last_name' => 'Fontana', 'role' => self::TEAM_ROLE_SLUG, 'is_specialist' => false],
        ['username' => 'bruno.alvorada', 'name' => 'Bruno', 'last_name' => 'Siqueira', 'role' => self::TEAM_ROLE_SLUG, 'is_specialist' => true],
        ['username' => 'camila.alvorada', 'name' => 'Camila', 'last_name' => 'Rocha', 'role' => self::TEAM_ROLE_SLUG, 'is_specialist' => true],
    ];

    public function run(CurrentFirm $currentFirm, ConsentTextService $consentTextService, SectorPageService $sectorPageService): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $firm = Firm::updateOrCreate(
            ['slug' => self::DEMO_SLUG],
            [
                'name' => 'Alvorada Contabilidade',
                'logo_text' => 'Alvorada Contabilidade',
                'short_name' => 'Alvorada',
                'tagline' => 'Contabilidade de perto para a empresa que está crescendo',
                'proof' => self::PROOF,
                // Every opt-in module but sub-brands; `channel` is no tenant's but Franco's (D18).
                'modules' => array_values(array_diff(FirmModules::OPT_IN, [FirmModules::BRANDS])),
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

        // Version 1 of its consent is the platform default (R13), its name filled when read.
        $consentTextService->startFirm($firm->id);

        // The public site's six sector pages, as `firm:create` gives a new firm (F3b1).
        $currentFirm->runAs($firm, fn () => $sectorPageService->startFirm($firm));

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
