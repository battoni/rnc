<?php

namespace Database\Factories;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Firm>
 */
class FirmFactory extends Factory
{
    protected $model = Firm::class;

    /** `firms.short_name` is 40 characters (rnc-4 R8). */
    private const SHORT_NAME_LENGTH = 40;

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
            'short_name' => mb_substr($name, 0, self::SHORT_NAME_LENGTH),
            'brand_primary' => '#00375a',
            'brand_accent' => '#14c3ff',
            'address' => fake()->streetAddress(),
            'phone' => '11 3000-0000',
            'mailbox' => fake()->unique()->safeEmail(),
            'business_hours' => [['weekdays' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '18:00']],
            // Every module, so the suite's implicit firm keeps every route it had before rnc-4; a test
            // about a module that is off sets `modules` itself (R16). The model's own default is none.
            'modules' => FirmModules::ALL,
        ];
    }
}
