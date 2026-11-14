<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Platform\Models\ViewAsSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open view-as session (rnc-4 F4c): a firm-less user entering a fresh firm.
 *
 * @extends Factory<ViewAsSession>
 */
class ViewAsSessionFactory extends Factory
{
    protected $model = ViewAsSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['firm_id' => null]),
            'firm_id' => Firm::factory(),
            'ip_address' => fake()->ipv4(),
            'started_at' => now(),
        ];
    }
}
