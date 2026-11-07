<?php

namespace Database\Factories;

use App\Modules\Auth\Models\LoginCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    protected $model = LoginCode::class;

    public const PLAIN_CODE = '123456';

    public function definition(): array
    {
        return [
            'phone' => '5511999998888',
            'code_hash' => Hash::make(self::PLAIN_CODE),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }
}
