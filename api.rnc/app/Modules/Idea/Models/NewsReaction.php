<?php

namespace App\Modules\Idea\Models;

use App\Models\User;
use App\Modules\Idea\Enums\NewsReactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NewsReactionType $type
 * @property int $count
 */
#[Fillable([
    'news_id',
    'user_id',
    'type',
    'count',
])]
class NewsReaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => NewsReactionType::class,
            'count' => 'integer',
        ];
    }

    /** @return BelongsTo<News, $this> */
    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
