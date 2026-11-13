<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Seeder;

/**
 * Franco's two sub-brands (rnc-3 R1, R29; D1), outside production only: the demo's Express and
 * Prime (`demos/franco-reforma-hub/src/store.js` 46–86 — names, short names, taglines, proof — and
 * `styles.css` 16–33 — the primary and accent of `[data-tenant]`). Their offer is not public yet:
 * the taglines and proof points are placeholders until Franco says what each one sells (demo W38).
 *
 * Runs inside `CurrentFirm::runAs` and writes `firm_id` itself (`DatabaseSeeder` runs without model
 * events). Re-running rewrites both rows to exactly this table.
 */
class BrandSeeder extends Seeder
{
    public const EXPRESS_SLUG = 'express';

    public const PRIME_SLUG = 'prime';

    /** @var array<string, array{name: string, short_name: string, tagline: string, brand_primary: string, brand_accent: string, proof: list<array{value: string, label: string}>}> */
    private const BRANDS = [
        self::EXPRESS_SLUG => [
            'name' => 'Franco Express Contabilidade',
            'short_name' => 'Franco Express',
            'tagline' => 'A contabilidade da Franco no ritmo da pequena empresa',
            'brand_primary' => '#3b2a86',
            'brand_accent' => '#ff8a3d',
            'proof' => [
                ['value' => '35 anos', 'label' => 'da Franco em São Paulo'],
                ['value' => 'Pequenas', 'label' => 'empresas e Simples Nacional'],
                ['value' => 'Online', 'label' => 'rotina sem papel'],
                ['value' => 'RNC', 'label' => 'Rede Nacional de Contabilidade'],
            ],
        ],
        self::PRIME_SLUG => [
            'name' => 'Franco Prime Contabilidade',
            'short_name' => 'Franco Prime',
            'tagline' => 'A Franco dedicada às empresas de maior porte',
            'brand_primary' => '#24262b',
            'brand_accent' => '#c9a35a',
            'proof' => [
                ['value' => '35 anos', 'label' => 'da Franco em São Paulo'],
                ['value' => 'Médias e grandes', 'label' => 'empresas, Presumido e Real'],
                ['value' => 'Sócio', 'label' => 'dedicado a cada conta'],
                ['value' => 'ISO 9001', 'label' => 'qualidade certificada'],
            ],
        ],
    ];

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $currentFirm->runAs($firm, function () use ($firm) {
            foreach (self::BRANDS as $slug => $brand) {
                $row = FirmBrand::query()->firstOrNew(['slug' => $slug]);
                $row->forceFill([...$brand, 'firm_id' => $firm->id, 'slug' => $slug, 'active' => true])->save();
            }
        });
    }
}
