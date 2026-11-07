<?php

namespace App\Modules\Idea\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Casts and the aggregates `NewsRepository::attachNewsAggregates` sets, stated because static
 * analysis reads the columns as strings and does not know the aggregates at all.
 *
 * @property CarbonImmutable|null $published_at
 * @property bool $is_published
 * @property array<string, int>|null $reaction_totals
 * @property array<string, int>|null $reaction_mine
 * @property int|null $read_count
 * @property bool|null $is_read_by_user
 */
#[Fillable([
    'title',
    'subtitle',
    'body',
    'link',
    'idea_id',
    'user_id',
    'published_at',
    'is_published',
])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    protected $table = 'news';

    protected static function newFactory(): NewsFactory
    {
        return NewsFactory::new();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'is_published' => 'boolean',
        ];
    }

    /** @return HasOne<NewsCover, $this> */
    public function cover(): HasOne
    {
        return $this->hasOne(NewsCover::class, 'news_id');
    }

    /**
     * Applause, one counter row per person per kind.
     *
     * @return HasMany<NewsArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(NewsArea::class);
    }

    /**
     * Applause, one counter row per person per kind.
     *
     * @return HasMany<NewsReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(NewsReaction::class, 'news_id');
    }

    /**
     * One row per person who has opened this entry.
     *
     * @return HasMany<NewsRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(NewsRead::class, 'news_id');
    }

    /**
     * The idea this entry answers, when it answers one. Most entries answer none.
     *
     * @return BelongsTo<Idea, $this>
     */
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
