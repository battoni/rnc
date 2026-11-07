<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * Franco Contabilidade, firm 1 (R1). Brand from the hub's identity (navy and cyan), public contact
 * and hours from the footer of franco-rnc.com.br (facts.md §1, fetched 05/11/2026).
 */
class FirmSeeder extends Seeder
{
    public const FRANCO_SLUG = 'franco';

    public function run(): void
    {
        Firm::updateOrCreate(
            ['slug' => self::FRANCO_SLUG],
            [
                'name' => 'Franco Contabilidade',
                'logo_text' => 'Franco Contabilidade',
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
    }
}
