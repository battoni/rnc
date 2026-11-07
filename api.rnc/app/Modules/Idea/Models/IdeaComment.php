<?php

namespace App\Modules\Idea\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\IdeaCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_official
 * @property CarbonImmutable|null $hidden_at
 */
#[Fillable([
    'idea_id',
    'user_id',
    'body',
    'is_official',
    'hidden_at',
])]
class IdeaComment extends Model
{
    /** @use HasFactory<IdeaCommentFactory> */
    use HasFactory;

    protected static function newFactory(): IdeaCommentFactory
    {
        return IdeaCommentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'hidden_at' => 'immutable_datetime',
        ];
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

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
