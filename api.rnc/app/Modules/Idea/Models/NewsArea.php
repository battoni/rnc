<?php

namespace App\Modules\Idea\Models;

use App\Modules\Idea\Enums\IdeaArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property IdeaArea $area
 */
class NewsArea extends Model
{
    protected $fillable = [
        'news_id',
        'area',
    ];

    protected function casts(): array
    {
        return [
            'area' => IdeaArea::class,
        ];
    }

    /** @return BelongsTo<News, $this> */
    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
