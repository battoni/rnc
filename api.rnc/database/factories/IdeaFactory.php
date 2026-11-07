<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
class IdeaFactory extends Factory
{
    protected $model = Idea::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'type' => IdeaType::IMPROVEMENT,
            'area' => IdeaArea::GENERAL,
            'status' => IdeaStatus::RECEIVED,
            'admin_note' => null,
            'votes_count' => 0,
            'comments_count' => 0,
            'merged_into_id' => null,
            'hidden_at' => null,
            'delivered_at' => null,
        ];
    }

    public function status(IdeaStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'delivered_at' => $status->isDelivered() ? now() : null,
        ]);
    }

    public function type(IdeaType $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }

    public function area(IdeaArea $area): static
    {
        return $this->state(fn (array $attributes) => ['area' => $area]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['hidden_at' => now()]);
    }

    /** Supported by N people. The counter is the read model; the rows are the truth. */
    public function supported(int $count): static
    {
        return $this->state(fn (array $attributes) => ['votes_count' => $count]);
    }
}
