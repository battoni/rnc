<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaVote>
 */
class IdeaVoteFactory extends Factory
{
    protected $model = IdeaVote::class;

    public function definition(): array
    {
        return [
            'idea_id' => Idea::factory(),
            'user_id' => User::factory(),
        ];
    }
}
