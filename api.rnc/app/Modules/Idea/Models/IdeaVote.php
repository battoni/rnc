<?php

namespace App\Modules\Idea\Models;

use App\Models\User;
use Database\Factories\IdeaVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'idea_id',
    'user_id',
])]
class IdeaVote extends Model
{
    /** @use HasFactory<IdeaVoteFactory> */
    use HasFactory;

    protected static function newFactory(): IdeaVoteFactory
    {
        return IdeaVoteFactory::new();
    }

    /** @return BelongsTo<Idea, $this> */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
