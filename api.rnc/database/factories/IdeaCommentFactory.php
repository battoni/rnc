<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaComment>
 */
class IdeaCommentFactory extends Factory
{
    protected $model = IdeaComment::class;

    public function definition(): array
    {
        return [
            'idea_id' => Idea::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_official' => false,
            'hidden_at' => null,
        ];
    }

    /** The maintainer's own reply — lifted out of the chronology by the interface. */
    public function official(): static
    {
        return $this->state(fn (array $attributes) => ['is_official' => true]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['hidden_at' => now()]);
    }
}
