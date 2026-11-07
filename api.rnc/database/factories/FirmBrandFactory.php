<?php

namespace Database\Factories;

use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An active sub-brand with a readable primary. Stamped with the current firm by `BelongsToFirm`
 * unless the test names one.
 *
 * @extends Factory<FirmBrand>
 */
class FirmBrandFactory extends Factory
{
    protected $model = FirmBrand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $short = 'Marca '.fake()->unique()->lexify('????');

        return [
            'slug' => Str::slug($short),
            'name' => $short.' Contabilidade',
            'short_name' => $short,
            'tagline' => fake()->sentence(6),
            'brand_primary' => '#1f4d3a',
            'brand_accent' => '#e0b04a',
            'proof' => array_fill(0, 4, ['value' => '10 anos', 'label' => 'de exemplo']),
            'active' => true,
        ];
    }
}
