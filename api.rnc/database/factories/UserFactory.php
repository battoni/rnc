<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory — plaintext, because the model's `hashed`
     * cast hashes it on save. It reads `seeding.password` (`SEEDER_PWD`) so a test knows the password it can log in with.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'role_id' => null,
            'firm_id' => fn (): int => $this->defaultFirmId(),
            // Always ACTIVE. INACTIVE is a state an administrator sets — a deactivated account is signed
            // out on its next request — so a factory that drew it at random made half of every
            // test's users unable to act. A test that needs one asks for it.
            'status' => UserStatus::ACTIVE,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= config('seeding.password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * The default firm (`franco` under `phpunit.xml`), created when the test has none: a signed-in
     * user with no firm works in the platform firm or is refused, and user reads are scoped by firm
     * (rnc-4 AM1, AM2). A test that means "no firm" passes `'firm_id' => null`.
     */
    private function defaultFirmId(): int
    {
        $slug = (string) config('firms.default_slug');

        return (int) (Firm::query()->where('slug', $slug)->value('id') ?? Firm::factory()->create(['slug' => $slug])->id);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
