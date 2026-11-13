<?php

namespace Database\Factories;

use App\Modules\Channel\Models\ChannelFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented RNC firm at contato, on the defaults of `config/channel.php`. Stamped with the
 * current firm by `BelongsToFirm` unless the test names one.
 *
 * @extends Factory<ChannelFirm>
 */
class ChannelFirmFactory extends Factory
{
    protected $model = ChannelFirm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Contábil '.fake()->unique()->lexify('????').' (exemplo)',
            'city' => 'Campinas-SP',
            'stage' => 'contato',
            'stage_at' => CarbonImmutable::now(),
            'monthly_fee_cents' => 350000,
            'share_bp' => 1500,
            'notes' => null,
        ];
    }
}
