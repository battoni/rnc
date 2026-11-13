<?php

namespace Database\Factories;

use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Firm>
 */
class FirmFactory extends Factory
{
    protected $model = Firm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_text' => $name,
            'brand_primary' => '#00375a',
            'brand_accent' => '#14c3ff',
            'address' => fake()->streetAddress(),
            'phone' => '11 3000-0000',
            'mailbox' => fake()->unique()->safeEmail(),
            'business_hours' => [['weekdays' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '18:00']],
        ];
    }
}
