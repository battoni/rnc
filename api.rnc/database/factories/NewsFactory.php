<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Idea\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'idea_id' => null,
            'user_id' => User::factory(),
            'published_at' => now(),
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => null, 'is_published' => false]);
    }
}
