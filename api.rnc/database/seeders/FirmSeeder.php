<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Database\Seeder;

/**
 * Franco Contabilidade, firm 1 (R1). Brand from the hub's identity (navy and cyan), public contact
 * and hours from the footer of franco-rnc.com.br (facts.md §1, fetched 05/11/2026).
 *
 * rnc-4 F2a (R8, R13): its short name and the landing's four proof points (what the site printed
 * from its own copy until now); no tagline yet, so the site keeps its own line. Its consent starts at
 * version 1 with the words it always had, the firm's name in the placeholder (spec C5).
 */
class FirmSeeder extends Seeder
{
    public const FRANCO_SLUG = 'franco';

    /** Franco's own consent since rnc-1 (AM12), `{escritorio}` where its name was. */
    public const CONSENT_V1 = 'Autorizo a {escritorio} a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

    /** @var list<array{value: string, label: string}> */
    private const PROOF = [
        ['value' => '35 anos', 'label' => 'em São Paulo'],
        ['value' => '500+', 'label' => 'clientes atendidos'],
        ['value' => 'ISO 9001', 'label' => 'qualidade certificada'],
        ['value' => 'RNC', 'label' => 'Rede Nacional de Contabilidade'],
    ];

    public function run(ConsentTextService $consentTextService): void
    {
        $firm = Firm::updateOrCreate(
            ['slug' => self::FRANCO_SLUG],
            [
                'name' => 'Franco Contabilidade',
                'logo_text' => 'Franco Contabilidade',
                'short_name' => 'Franco',
                'proof' => self::PROOF,
                'brand_primary' => '#00375a',
                'brand_accent' => '#14c3ff',
                'address' => 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
                'phone' => '11 3732-5500',
                'mailbox' => 'saf@franco-rnc.com.br',
                'business_hours' => [
                    ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
                    ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
                ],
            ]
        );

        $consentTextService->startFirm($firm->id, self::CONSENT_V1);
    }
}
